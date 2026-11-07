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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationAllowList;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CreatableClassTrait;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
final readonly class AdHocImportTransformationService implements AdHocImportTransformationServiceInterface
{
    use CreatableClassTrait;
    use CurrentUserResolverTrait;

    public function __construct(
        private FileStore $fileStore,
        private FileInspector $fileInspector,
        private SecurityServiceInterface $securityService, // NOSONAR php:S1068 read by CurrentUserResolverTrait
        private ClassDefinitionServiceInterface $classDefinitionService, // NOSONAR php:S1068 read by CreatableClassTrait
        private MappingSuggestionServiceInterface $mappingSuggestionService,
        private TransformationChain $transformationChain,
    ) {
    }

    public function previewTransformations(
        string $fileId,
        AdHocImportTransformationParameters $parameters
    ): AdHocImportTransformationResponse {
        $user = $this->resolveCurrentUser();
        $classId = $parameters->getClassId();
        $this->assertMayCreate($classId);

        // the fields the mapping offers
        $field = $parameters->getField();
        $fields = array_diff(
            array_column($this->mappingSuggestionService->loadTargets($classId), 'key'),
            AdHocImportMappingService::NO_MAPPING_TARGETS
        );
        if (!in_array($field, $fields, true)) {
            throw new NotFoundHttpException(sprintf('Field %s not found', $field));
        }

        $steps = [];
        foreach ($parameters->getTransformationPipeline() as $position => $step) {
            $steps[] = TransformationAllowList::step($step, $position + 1);
        }

        $type = $this->fileStore->findType($user->getId(), $fileId)
            ?? throw new NotFoundHttpException(sprintf('File %s not found', $fileId));
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
}
