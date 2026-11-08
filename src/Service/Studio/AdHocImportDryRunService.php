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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\ConvertedColumn;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ColumnWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ConfigurationItemWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunner;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\FieldWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ImportField;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspection;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationAllowList;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplier;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\MatchingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcherFactory;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CreatableClassTrait;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\TargetFolderTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\User;
use Pimcore\Tool;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @phpstan-type Target array{key: string, title: string, localized: bool}
 * @phpstan-type Objects array{
 *     classId: string,
 *     matchBy: string,
 *     matchField: string|null,
 *     matchLanguage: string|null,
 *     matchColumn: string,
 *     newObjectsFolder: string|null
 * }
 *
 * @internal
 */
final readonly class AdHocImportDryRunService implements AdHocImportDryRunServiceInterface // NOSONAR php:S1200 one entry point for the dry run
{
    use CreatableClassTrait;
    use CurrentUserResolverTrait;
    use TargetFolderTrait;

    private const string DIRECT_TARGET = 'direct';

    // no file names a column so: an item reads it as empty
    private const string MISSING_COLUMN = "\0";

    public function __construct(
        private FileStore $fileStore,
        private FileInspector $fileInspector,
        private SecurityServiceInterface $securityService, // NOSONAR php:S1068 read by CurrentUserResolverTrait
        private ClassDefinitionServiceInterface $classDefinitionService, // NOSONAR php:S1068 read by CreatableClassTrait
        private DataObjectResolverInterface $dataObjectResolver, // NOSONAR php:S1068 read by TargetFolderTrait
        private MappingSuggestionServiceInterface $mappingSuggestionService,
        private AdHocImportConfigurationServiceInterface $configurationService,
        private TransformationChain $transformationChain,
        private MappingApplier $mappingApplier,
        private RowMatcherFactory $rowMatcherFactory,
        private DryRunner $dryRunner,
        private DryRunMessages $messages,
    ) {
    }

    public function dryRun(string $fileId, AdHocImportDryRunParameters $parameters): AdHocImportDryRunResponse
    {
        $user = $this->resolveCurrentUser();
        $type = $this->fileStore->findType($user->getId(), $fileId)
            ?? throw new NotFoundHttpException(sprintf('File %s not found', $fileId));
        $targetFolder = $this->assertMayImportInto($parameters->getFolderId(), $user);
        $targetPath = $targetFolder->getRealFullPath();

        $configuration = $parameters->getConfiguration() === null
            ? null
            : $this->configurationService->loadConfiguration($parameters->getConfiguration());
        $objects = $configuration === null
            ? $this->objectsOf($parameters)
            : $this->objectsOfConfiguration($configuration['configuration'], $parameters->getConfigurationColumns());
        $this->assertMayCreate($objects['classId']);
        $newObjectPath = $this->newObjectPath($objects['newObjectsFolder'], $targetPath, $user);

        $path = $this->fileStore->localPath($user->getId(), $fileId, $type);
        $inspection = $this->fileInspector->inspect($path, $type, $parameters->toSettingsInput());
        if ($inspection->refusal !== null) {
            throw new InvalidArgumentException(sprintf('The file cannot be imported: %s', $inspection->refusal->value));
        }
        $columns = array_column($inspection->columns, 'label', 'dataIndex');
        $this->assertColumn($columns, $objects['matchColumn']);

        try {
            $rows = [...$this->fileInspector->readRows($path, $type, $inspection)];
            $matcher = $this->rowMatcherFactory->create(new MatchingConfiguration(
                $objects['classId'],
                $objects['matchColumn'],
                $targetPath,
                $objects['matchBy'] === AdHocImportDryRunParameters::MATCH_BY_FIELD ? $objects['matchField'] : null,
                $objects['matchLanguage'] ?? '',
                $newObjectPath === $targetPath ? null : $newObjectPath
            ));
        } catch (InvalidConfigurationException|InvalidInputException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), $exception);
        }

        $targets = [];
        foreach ($this->mappingSuggestionService->loadTargets($objects['classId']) as $target) {
            $targets[$target['key']] = $target;
        }
        $writers = $configuration === null
            ? $this->columnWriters($parameters->getColumns(), $columns, $targets, $objects['classId'], $rows, $user)
            : $this->configurationWriters(
                $configuration['mapping'],
                $parameters->getConfigurationColumns(),
                $columns,
                $targets
            );
        $shown = $this->shown($writers, $objects);

        $results = $this->dryRunner->run(new DryRunPlan(
            $rows,
            $this->firstRowNumber($inspection),
            $matcher,
            $writers,
            $shown,
            $this->matchFieldLabel($objects, $targets, $user),
            $targetPath,
            $newObjectPath,
            $user
        ));
        $this->storeSkippedRows($user, $fileId, $columns, $rows, $results, $this->firstRowNumber($inspection));

        return new AdHocImportDryRunResponse(
            $columns[$objects['matchColumn']],
            $newObjectPath,
            array_map(static function (int $position) use ($writers): array {
                $field = $writers[$position]->field();

                return ['key' => $field->key, 'language' => $field->language, 'label' => $field->label];
            }, $shown),
            array_map(static fn (DryRunRow $row): array => [
                'row' => $row->row,
                'matchValue' => $row->matchValue,
                'action' => $row->action,
                'error' => $row->error,
                'values' => $row->values,
                'invalid' => $row->invalid,
            ], $results)
        );
    }

    public function skippedRows(string $fileId, string $errorMode): string
    {
        if (!in_array($errorMode, [self::SKIP_ROWS, self::WITHOUT_INVALID_VALUES], true)) {
            throw new InvalidArgumentException(sprintf('Unknown error mode %s', $errorMode));
        }
        $user = $this->resolveCurrentUser();
        $stored = $this->fileStore->readSkippedRows($user->getId(), $fileId);
        $skipped = $stored === null ? null : json_decode($stored, true);
        if (!is_array($skipped)) {
            throw new NotFoundHttpException(sprintf('No dry run of file %s found', $fileId));
        }

        $csv = fopen('php://temp', 'r+b');
        fputcsv(
            $csv,
            [
                $this->messages->columnName('row', $user),
                ...$skipped['columns'],
                $this->messages->columnName('error', $user),
            ],
            escape: ''
        );
        foreach ($skipped['rows'] as $row) {
            // without the invalid values a row that finds its object is imported
            if ($errorMode === self::WITHOUT_INVALID_VALUES && $row['matched']) {
                continue;
            }
            fputcsv($csv, [$row['row'], ...$row['values'], $row['error']], escape: '');
        }
        rewind($csv);
        $content = (string) stream_get_contents($csv);
        fclose($csv);

        return $content;
    }

    /**
     * @return Objects
     */
    private function objectsOf(AdHocImportDryRunParameters $parameters): array
    {
        $matchField = $parameters->getMatchField();
        if ($parameters->getMatchBy() === AdHocImportDryRunParameters::MATCH_BY_FIELD
            && ($matchField === null || $matchField === '')
        ) {
            throw new InvalidArgumentException('Matching by a field needs the field.');
        }

        return [
            'classId' => $parameters->getClassId(),
            'matchBy' => $parameters->getMatchBy(),
            'matchField' => $parameters->getMatchField(),
            'matchLanguage' => $parameters->getMatchLanguage(),
            'matchColumn' => $parameters->getMatchColumn(),
            'newObjectsFolder' => $parameters->getNewObjectsFolder(),
        ];
    }

    /**
     * A configuration's class, match and folder replace those of the file step; its match column is found in the
     * file as the caller found it.
     *
     * @param array{
     *     classId: string|null,
     *     matchBy: string|null,
     *     matchField: string|null,
     *     matchLanguage: string|null,
     *     matchColumn: string|null,
     *     newObjectsFolder: string|null
     * } $configuration
     * @param array<mixed> $fileColumns
     *
     * @return Objects
     */
    private function objectsOfConfiguration(array $configuration, array $fileColumns): array
    {
        $matchColumn = $fileColumns[$configuration['matchColumn'] ?? ''] ?? null;
        if (!is_string($matchColumn) || $configuration['classId'] === null || $configuration['matchBy'] === null) {
            throw new InvalidArgumentException('The file lacks the column the configuration matches by.');
        }

        return [
            'classId' => $configuration['classId'],
            'matchBy' => $configuration['matchBy'],
            'matchField' => $configuration['matchField'],
            'matchLanguage' => $configuration['matchLanguage'],
            'matchColumn' => $matchColumn,
            'newObjectsFolder' => $configuration['newObjectsFolder'],
        ];
    }

    /**
     * @throws NotFoundHttpException|ForbiddenException
     */
    private function newObjectPath(?string $folderPath, string $targetPath, User $user): string
    {
        if ($folderPath === null || $folderPath === '') {
            return $targetPath;
        }

        $folder = $this->dataObjectResolver->getByPath($folderPath)
            ?? throw new NotFoundHttpException(sprintf('Data object folder %s not found', $folderPath));
        if (!$folder->isAllowed('create', $user)) {
            throw new ForbiddenException(sprintf('Access denied to data object folder %s', $folderPath));
        }

        return $folder->getRealFullPath();
    }

    /**
     * @param array<int|string, string> $columns label per data index
     */
    private function assertColumn(array $columns, mixed $column): void
    {
        if (!is_string($column) || !array_key_exists($column, $columns)) {
            throw new InvalidArgumentException(sprintf(
                'The file has no column %s.',
                is_scalar($column) ? (string) $column : get_debug_type($column)
            ));
        }
    }

    /**
     * @param list<mixed> $mapped
     * @param array<int|string, string> $columns
     * @param array<string, Target> $targets
     * @param list<array<int|string, mixed>> $rows
     *
     * @return list<FieldWriter>
     */
    private function columnWriters(
        array $mapped,
        array $columns,
        array $targets,
        string $classId,
        array $rows,
        User $user
    ): array {
        $accepted = $this->transformationChain->acceptedTypes($classId);
        $writers = [];
        $taken = [];
        foreach ($mapped as $position => $entry) {
            $entry = is_array($entry) ? $entry : [];
            $column = $entry['column'] ?? null;
            $this->assertColumn($columns, $column);
            if (($entry['key'] ?? null) === null) {
                $field = $this->field($entry['field'] ?? null, $entry['language'] ?? null, $targets);
                $fieldAccepts = $accepted[(string) $field->key] ?? [];
            } else {
                [$field, $fieldAccepts] = $this->storeField(
                    $entry['field'] ?? null,
                    $entry['key'],
                    $entry['language'] ?? null,
                    $classId
                );
            }

            $key = json_encode([$field->key, $field->language, $field->storeKey?->id()]);
            if (isset($taken[$key])) {
                throw new InvalidArgumentException(sprintf('Two columns go into field %s.', $field->label));
            }
            $taken[$key] = true;

            $values = array_map(
                static fn (array $row): ?string => is_scalar($row[$column] ?? null) ? (string) $row[$column] : null,
                $rows
            );
            // a store key of a type no transformation leads to: the picker offers it, its values do not fit
            $converted = $field->storeKey !== null && $fieldAccepts === []
                ? ConvertedColumn::unwritable($values)
                : $this->convertedColumn(
                    $values,
                    $this->steps($entry['transformationPipeline'] ?? []),
                    $fieldAccepts,
                    $user
                );
            if ($converted === null) {
                throw new InvalidArgumentException(sprintf(
                    'Column %d does not fit field %s with its transformations.',
                    $position + 1,
                    $field->key
                ));
            }

            $writers[] = new ColumnWriter($field, $column, $converted);
        }

        return $writers;
    }

    /**
     * @return list<array{type: string, settings: array<string, string>}>
     */
    private function steps(mixed $pipeline): array
    {
        $steps = [];
        foreach (is_array($pipeline) ? array_values($pipeline) : [null] as $position => $step) {
            $steps[] = TransformationAllowList::step($step, $position + 1);
        }

        return $steps;
    }

    /**
     * @param list<string|null> $values
     * @param list<array{type: string, settings: array<string, string>}> $steps
     * @param list<string> $accepted
     */
    private function convertedColumn(array $values, array $steps, array $accepted, User $user): ?ConvertedColumn
    {
        try {
            return $this->transformationChain->convertColumn($values, $steps, $accepted, $user);
        } catch (InvalidConfigurationException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), $exception);
        }
    }

    /**
     * @param array<string, Target> $targets
     */
    private function field(mixed $key, mixed $language, array $targets): ImportField
    {
        $target = is_string($key) && !in_array($key, AdHocImportMappingService::NO_MAPPING_TARGETS, true)
            ? $targets[$key] ?? null
            : null;
        if ($target === null) {
            throw new InvalidArgumentException(sprintf(
                'Field %s cannot be imported into.',
                is_scalar($key) ? (string) $key : get_debug_type($key)
            ));
        }

        $language = is_string($language) && $language !== '' ? $language : null;
        if ($target['localized'] !== ($language !== null)
            || ($language !== null && !Tool::isValidLanguage($language))
        ) {
            throw new InvalidArgumentException(sprintf('Field %s needs a valid language, and only it.', $key));
        }

        return new ImportField($target['key'], $language, $this->title($target));
    }

    /**
     * A key of one of the class's classification stores, and the result types it takes by its own type.
     *
     * @return array{ImportField, list<string>}
     */
    private function storeField(mixed $fieldName, mixed $keyId, mixed $language, string $classId): array
    {
        $store = is_string($fieldName) ? ClassDefinition::getById($classId)?->getFieldDefinition($fieldName) : null;
        if (!$store instanceof Classificationstore) {
            throw new InvalidArgumentException(sprintf(
                'Field %s is no classification store.',
                is_scalar($fieldName) ? (string) $fieldName : get_debug_type($fieldName)
            ));
        }

        $storeKey = StoreKey::fromId($keyId);
        $relation = $storeKey === null
            ? null
            : KeyGroupRelation::getByGroupAndKeyId($storeKey->groupId, $storeKey->keyId);
        $group = $relation === null ? null : GroupConfig::getById($relation->getGroupId());
        if ($storeKey === null || $relation === null || $group?->getStoreId() !== $store->getStoreId()) {
            throw new InvalidArgumentException(sprintf(
                'Key %s is no key of classification store %s.',
                is_scalar($keyId) ? (string) $keyId : get_debug_type($keyId),
                $fieldName
            ));
        }

        // a localized store takes its default values without a language
        $language = is_string($language) && $language !== '' ? $language : null;
        if ($language !== null && (!$store->isLocalized() || !Tool::isValidLanguage($language))) {
            throw new InvalidArgumentException(sprintf('Field %s needs a valid language, and only it.', $fieldName));
        }

        return [
            new ImportField($fieldName, $language, $group->getName() . ' › ' . $relation->getName(), $storeKey),
            $this->transformationChain->acceptedTypesOf($relation->getType()),
        ];
    }

    /**
     * The configuration's items with the file's columns; an item reading none of them imports nothing.
     *
     * @param array<int|string, mixed> $mapping
     * @param array<mixed> $fileColumns per column the configuration reads, the file's data index
     * @param array<int|string, string> $columns
     * @param array<string, Target> $targets
     *
     * @return list<FieldWriter>
     */
    private function configurationWriters(array $mapping, array $fileColumns, array $columns, array $targets): array
    {
        foreach ($fileColumns as $fileColumn) {
            $this->assertColumn($columns, $fileColumn);
        }

        $writers = [];
        foreach ($mapping as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $read = $this->readColumns($item['dataSourceIndex'] ?? [], $fileColumns);
            $found = array_values(array_filter($read, static fn (?string $column): bool => $column !== null));
            if ($found === []) {
                continue;
            }
            $item['dataSourceIndex'] = array_map(
                static fn (?string $column): string => $column ?? self::MISSING_COLUMN,
                $read
            );

            try {
                $prepared = $this->mappingApplier->prepare([$index => $item]);
            } catch (InvalidConfigurationException $exception) {
                throw new InvalidArgumentException($exception->getMessage(), $exception);
            }

            $writers[] = new ConfigurationItemWriter($this->configurationField($item, $targets), $prepared, $found);
        }

        return $writers;
    }

    /**
     * Per source the item reads, the file's column; null where the file lacks it.
     *
     * @param array<mixed> $fileColumns
     *
     * @return list<string|null>
     */
    private function readColumns(mixed $sources, array $fileColumns): array
    {
        $read = [];
        foreach ((array) $sources as $source) {
            $column = is_scalar($source) ? $fileColumns[(string) $source] ?? null : null;
            $read[] = is_string($column) ? $column : null;
        }

        return $read;
    }

    /**
     * @param array<mixed> $item
     * @param array<string, Target> $targets
     */
    private function configurationField(array $item, array $targets): ImportField
    {
        $target = is_array($item['dataTarget'] ?? null) ? $item['dataTarget'] : [];
        $settings = is_array($target['settings'] ?? null) ? $target['settings'] : [];
        $fieldName = is_string($settings['fieldName'] ?? null) ? $settings['fieldName'] : null;
        $label = is_string($item['label'] ?? null) && $item['label'] !== '' ? $item['label'] : (string) $fieldName;

        if (($target['type'] ?? null) !== self::DIRECT_TARGET || $fieldName === null) {
            return new ImportField(null, null, $label);
        }

        $language = is_string($settings['language'] ?? null) && $settings['language'] !== ''
            ? $settings['language']
            : null;

        return new ImportField(
            $fieldName,
            $language,
            isset($targets[$fieldName]) ? $this->title($targets[$fieldName]) : $label
        );
    }

    /**
     * The fields the preview shows: all but the one matched by, whose value the row's match value is.
     *
     * @param list<FieldWriter> $writers
     * @param Objects $objects
     *
     * @return list<int>
     */
    private function shown(array $writers, array $objects): array
    {
        $shown = [];
        foreach ($writers as $position => $writer) {
            $field = $writer->field();
            $isMatchField = $objects['matchBy'] === AdHocImportDryRunParameters::MATCH_BY_FIELD
                && $field->key === $objects['matchField']
                && ($field->language ?? '') === ($objects['matchLanguage'] ?? '');
            if (!$isMatchField) {
                $shown[] = $position;
            }
        }

        return $shown;
    }

    /**
     * @param Objects $objects
     * @param array<string, Target> $targets
     */
    private function matchFieldLabel(array $objects, array $targets, User $user): string
    {
        if ($objects['matchBy'] !== AdHocImportDryRunParameters::MATCH_BY_FIELD) {
            return $this->messages->objectIdLabel($user);
        }

        $field = (string) $objects['matchField'];

        return isset($targets[$field]) ? $this->title($targets[$field]) : $field;
    }

    /**
     * The field's title as the class gives it; a class attribute's title ends in its key.
     *
     * @param Target $target
     */
    private function title(array $target): string
    {
        $keySuffix = ' [' . $target['key'] . ']';
        $title = str_ends_with($target['title'], $keySuffix)
            ? substr($target['title'], 0, -strlen($keySuffix))
            : $target['title'];

        return $title === '' ? $target['key'] : $title;
    }

    private function firstRowNumber(FileInspection $inspection): int
    {
        return $inspection->settings['hasHeaderRow'] ? 2 : 1;
    }

    /**
     * @param array<int|string, string> $columns
     * @param list<array<int|string, mixed>> $rows
     * @param list<DryRunRow> $results
     */
    private function storeSkippedRows(
        User $user,
        string $fileId,
        array $columns,
        array $rows,
        array $results,
        int $firstRowNumber
    ): void {
        $skipped = [];
        foreach ($results as $result) {
            if (!$result->hasErrors()) {
                continue;
            }
            $row = $rows[$result->row - $firstRowNumber];
            $skipped[] = [
                'row' => $result->row,
                'values' => array_map(
                    static fn (int|string $column): string => is_scalar($row[$column] ?? null)
                        ? (string) $row[$column]
                        : '',
                    array_keys($columns)
                ),
                'error' => $result->error ?? implode('; ', array_column($result->invalid, 'message')),
                'matched' => $result->action !== null,
            ];
        }

        $this->fileStore->storeSkippedRows(
            $user->getId(),
            $fileId,
            json_encode(['columns' => array_values($columns), 'rows' => $skipped], JSON_THROW_ON_ERROR)
        );
    }
}
