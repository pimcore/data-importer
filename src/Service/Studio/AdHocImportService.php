<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspection;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\RefusalReason;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationAllowList;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportMappingResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
final readonly class AdHocImportService implements AdHocImportServiceInterface
{
    use CurrentUserResolverTrait;

    private const int MAX_FILE_SIZE = 52428800; // 50MB, as for the preview files of a configuration

    // a system attribute of a class with variants; objects cannot be matched by it
    private const string OBJECT_TYPE_ATTRIBUTE = 'type';

    // An ad hoc import keeps objects where it finds them and names new ones by their match value
    private const array NO_MAPPING_TARGETS = ['key', 'path', self::OBJECT_TYPE_ATTRIBUTE];

    public function __construct(
        private FileStore $fileStore,
        private FileInspector $fileInspector,
        private SecurityServiceInterface $securityService, // NOSONAR php:S1068 read by CurrentUserResolverTrait
        private DataObjectResolverInterface $dataObjectResolver,
        private DataTypeServiceInterface $dataTypeService,
        private ClassDefinitionServiceInterface $classDefinitionService,
        private MappingSuggestionServiceInterface $mappingSuggestionService,
        private ClassDefinitionResolverInterface $classDefinitionResolver,
        private TransformationChain $transformationChain,
    ) {
    }

    public function upload(UploadedFile $file, int $folderId): AdHocImportFileResponse
    {
        $user = $this->resolveCurrentUser();
        $this->assertMayImportInto($folderId, $user);

        if (!$file->isValid()) {
            throw new EnvironmentException($file->getErrorMessage());
        }
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new MaxFileSizeExceededException(self::MAX_FILE_SIZE);
        }

        $type = FileType::fromFileName($file->getClientOriginalName());
        if ($type === null) {
            return new AdHocImportFileResponse(FileInspector::MAX_ROWS, refusal: RefusalReason::WrongFormat->value);
        }

        $fileId = $this->fileStore->store($user->getId(), $type, $file->getPathname());
        $path = $this->fileStore->localPath($user->getId(), $fileId, $type);

        if (!$this->fileInspector->isReadable($path, $type)) {
            $this->fileStore->delete($user->getId(), $fileId, $type);

            return new AdHocImportFileResponse(
                FileInspector::MAX_ROWS,
                type: $type->value,
                refusal: RefusalReason::Unreadable->value
            );
        }

        $inspection = $this->fileInspector->inspect($path, $type, new SettingsInput());

        if ($inspection->refusal !== null && !$this->settingsCouldHelp($type, $inspection)) {
            $this->fileStore->delete($user->getId(), $fileId, $type);
            $fileId = null;
        }

        return $this->response($fileId, $type, $inspection);
    }

    public function inspect(string $fileId, SettingsInput $settings): AdHocImportFileResponse
    {
        $user = $this->resolveCurrentUser();
        $type = $this->requireType($user, $fileId);

        return $this->response(
            $fileId,
            $type,
            $this->fileInspector->inspect($this->fileStore->localPath($user->getId(), $fileId, $type), $type, $settings)
        );
    }

    public function delete(string $fileId): void
    {
        $user = $this->resolveCurrentUser();

        $this->fileStore->delete($user->getId(), $fileId, $this->requireType($user, $fileId));
    }

    public function loadMatchFields(string $classId): ClassAttributesResponse
    {
        $this->assertMayCreate($classId);

        $attributes = $this->dataTypeService->loadClassAttributes(
            $classId,
            [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::NUMERIC],
            false,
            false,
            false
        );

        $fields = new ClassAttributesResponse(array_values(array_filter(
            $attributes->getAttributes(),
            static fn (array $attribute): bool => $attribute['key'] !== self::OBJECT_TYPE_ATTRIBUTE
        )));
        foreach ($attributes->getAdditionalAttributes() as $key => $value) {
            $fields->addAdditionalAttribute($key, $value);
        }

        return $fields;
    }

    public function loadMapping(MappingSuggestionParameters $parameters): AdHocImportMappingResponse
    {
        $classId = $parameters->getClassId();
        $this->assertMayCreate($classId);

        $class = $this->classDefinitionResolver->getById($classId);
        $accepted = $this->transformationChain->acceptedTypes($classId);
        $fields = [];
        foreach ($this->mappingTargets($classId) as $target) {
            $fields[] = [
                ...$target,
                'mandatory' => $this->isMandatory($class, $target['key']),
                'takesColumnAsIs' => $this->transformationChain->takesColumnAsIs($accepted[$target['key']] ?? []),
            ];
        }

        $suggestions = array_values(array_filter(
            $this->mappingSuggestionService->suggest($parameters)->getSuggestions(),
            static fn (array $suggestion): bool => !in_array(
                $suggestion['targetFieldName'],
                self::NO_MAPPING_TARGETS,
                true
            )
        ));

        return new AdHocImportMappingResponse($fields, $suggestions);
    }

    public function previewTransformations(
        string $fileId,
        AdHocImportTransformationParameters $parameters
    ): AdHocImportTransformationResponse {
        $user = $this->resolveCurrentUser();
        $classId = $parameters->getClassId();
        $this->assertMayCreate($classId);

        $field = $parameters->getField();
        if (!in_array($field, array_column($this->mappingTargets($classId), 'key'), true)) {
            throw new NotFoundHttpException(sprintf('Field %s not found', $field));
        }

        $steps = [];
        foreach ($parameters->getTransformationPipeline() as $position => $step) {
            $steps[] = TransformationAllowList::step($step, $position + 1);
        }

        $type = $this->requireType($user, $fileId);
        $inspection = $this->fileInspector->inspect(
            $this->fileStore->localPath($user->getId(), $fileId, $type),
            $type,
            $parameters->toSettingsInput()
        );
        $position = array_search($parameters->getColumn(), array_column($inspection->columns, 'dataIndex'), true);
        if ($position === false) {
            throw new NotFoundHttpException(sprintf('Column %s not found', $parameters->getColumn()));
        }

        try {
            return $this->transformationChain->run(
                array_map(static fn (array $row): ?string => $row[$position] ?? null, $inspection->firstRows),
                $steps,
                $this->transformationChain->acceptedTypes($classId)[$field] ?? [],
                $user
            );
        } catch (InvalidConfigurationException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), $exception);
        }
    }

    /**
     * The fields a column can go into.
     *
     * @return list<array{key: string, title: string, localized: bool, transformationResultType: string}>
     */
    private function mappingTargets(string $classId): array
    {
        return array_values(array_filter(
            $this->mappingSuggestionService->loadTargets($classId),
            static fn (array $target): bool => !in_array($target['key'], self::NO_MAPPING_TARGETS, true)
        ));
    }

    private function assertMayCreate(string $classId): void
    {
        $creatable = array_map(
            static fn (ClassDefinitionList $class): string => $class->getId(),
            $this->classDefinitionService->getClassDefinitionCollection(creatableOnly: true)
        );
        if (!in_array($classId, $creatable, true)) {
            throw new ForbiddenException(sprintf('Access denied to class %s', $classId));
        }
    }

    // a localized field is found among the class's localized fields
    private function isMandatory(?ClassDefinition $class, string $key): bool
    {
        return $class?->getFieldDefinition($key)?->getMandatory() ?? false;
    }

    /**
     * Whether to keep a refused file for another try: another sheet, or the settings of a CSV that does not fit its
     * header.
     */
    private function settingsCouldHelp(FileType $type, FileInspection $inspection): bool
    {
        return match ($type) {
            FileType::Csv => $inspection->refusal === RefusalReason::Unreadable,
            FileType::Xlsx => count($inspection->sheets) > 1,
        };
    }

    private function assertMayImportInto(int $folderId, User $user): void
    {
        $folder = $this->dataObjectResolver->getById($folderId);
        if (!$folder instanceof Folder) {
            throw new NotFoundHttpException(sprintf('Data object folder %d not found', $folderId));
        }

        if (!$folder->isAllowed('create', $user) || !$folder->isAllowed('save', $user)) {
            throw new ForbiddenException(sprintf('Access denied to data object folder %d', $folderId));
        }
    }

    private function requireType(User $user, string $fileId): FileType
    {
        return $this->fileStore->findType($user->getId(), $fileId)
            ?? throw new NotFoundHttpException(sprintf('File %s not found', $fileId));
    }

    private function response(?string $fileId, FileType $type, FileInspection $inspection): AdHocImportFileResponse
    {
        return new AdHocImportFileResponse(
            FileInspector::MAX_ROWS,
            $fileId,
            $type->value,
            $inspection->refusal?->value,
            $inspection->settings['delimiter'] ?? null,
            $inspection->detectedDelimiter,
            $inspection->settings['enclosure'] ?? null,
            $inspection->settings['escape'] ?? null,
            $inspection->settings['hasHeaderRow'],
            $inspection->settings['sheetName'] ?? null,
            $inspection->sheets,
            $inspection->rowCount,
            $inspection->columns,
            $inspection->firstRows
        );
    }
}
