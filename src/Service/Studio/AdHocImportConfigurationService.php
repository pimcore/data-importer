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

use Closure;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ColumnKeying;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ConfigurationUnusableReason;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcherFactory;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportConfigurationsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @phpstan-import-type ImportConfiguration from AdHocImportConfigurationsResponse
 *
 * @internal
 */
final readonly class AdHocImportConfigurationService implements AdHocImportConfigurationServiceInterface
{
    use CurrentUserResolverTrait;

    private const string ADAPTER_TYPE = 'dataImporterDataObject';

    // the strategies whose result an ad hoc import can take: by the object ID or a field, into one folder
    private const array LOADING_BY = ['id' => 'id', 'attribute' => 'field'];

    private const string STATIC_PATH = 'staticPath';

    /**
     * @var Closure(): iterable<Configuration>
     */
    private Closure $listConfigurations;

    /**
     * @param (Closure(): iterable<Configuration>)|null $listConfigurations defaults to Data Hub's listing;
     *                                                                       injectable for tests
     */
    public function __construct(
        private SecurityServiceInterface $securityService, // NOSONAR php:S1068 read by CurrentUserResolverTrait
        private ClassDefinitionServiceInterface $classDefinitionService,
        private ClassDefinitionResolverInterface $classDefinitionResolver,
        private DataObjectResolverInterface $dataObjectResolver,
        private RowMatcherFactory $rowMatcherFactory,
        ?Closure $listConfigurations = null,
    ) {
        $this->listConfigurations = $listConfigurations ?? static fn (): array => Configuration::getList();
    }

    public function listConfigurations(): AdHocImportConfigurationsResponse
    {
        $user = $this->resolveCurrentUser();
        $creatable = $this->creatableClasses();

        $configurations = [];
        foreach (($this->listConfigurations)() as $configuration) {
            if ($configuration->getType() === self::ADAPTER_TYPE
                && $configuration->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ, $user)
            ) {
                $configurations[] = $this->describe($configuration, $creatable, $user);
            }
        }

        usort(
            $configurations,
            static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name'])
        );

        return new AdHocImportConfigurationsResponse($configurations);
    }

    public function loadConfiguration(string $name): array
    {
        $user = $this->resolveCurrentUser();

        foreach (($this->listConfigurations)() as $configuration) {
            if ($configuration->getName() !== $name
                || $configuration->getType() !== self::ADAPTER_TYPE
                || !$configuration->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ, $user)
            ) {
                continue;
            }

            $described = $this->describe($configuration, $this->creatableClasses(), $user);
            if ($described['unusable'] !== null) {
                throw new InvalidArgumentException(sprintf(
                    'An ad hoc import cannot use configuration %s: %s',
                    $name,
                    $described['unusable']
                ));
            }
            $data = $configuration->getConfiguration();

            return [
                'configuration' => $described,
                'mapping' => is_array($data['mappingConfig'] ?? null) ? $data['mappingConfig'] : [],
            ];
        }

        throw new NotFoundHttpException(sprintf('Configuration %s not found', $name));
    }

    /**
     * @return array<string, true>
     */
    private function creatableClasses(): array
    {
        $creatable = [];
        foreach ($this->classDefinitionService->getClassDefinitionCollection(creatableOnly: true) as $class) {
            $creatable[$class->getId()] = true;
        }

        return $creatable;
    }

    /**
     * @param array<string, true> $creatable
     *
     * @return ImportConfiguration
     */
    private function describe(Configuration $configuration, array $creatable, User $user): array
    {
        $data = $configuration->getConfiguration();
        $resolver = is_array($data['resolverConfig'] ?? null) ? $data['resolverConfig'] : [];

        $classId = $this->text($resolver['dataObjectClassId'] ?? null);
        $match = $this->match(is_array($resolver['loadingStrategy'] ?? null) ? $resolver['loadingStrategy'] : []);
        $folder = $this->folder(
            is_array($resolver['createLocationStrategy'] ?? null) ? $resolver['createLocationStrategy'] : []
        );

        $class = $classId === null ? null : $this->classDefinitionResolver->getById($classId);

        $unusable = match (true) {
            $class === null || !isset($creatable[$classId]) => ConfigurationUnusableReason::ClassNotCreatable,
            $match === null || !$this->matchable($class, $match) => ConfigurationUnusableReason::Matching,
            $folder === null => ConfigurationUnusableReason::Folder,
            default => $this->folderUnusable($folder, $user),
        };

        $columns = $this->mappedColumns(is_array($data['mappingConfig'] ?? null) ? $data['mappingConfig'] : []);
        if ($match !== null) {
            $columns = array_values(array_unique([$match['column'], ...$columns]));
        }

        return [
            'name' => (string)$configuration->getName(),
            'classId' => $classId,
            'className' => $class?->getName(),
            'matchBy' => $match['by'] ?? null,
            'matchField' => $match['field'] ?? null,
            'matchLanguage' => $match['language'] ?? null,
            'matchColumn' => $match['column'] ?? null,
            'newObjectsFolder' => $folder,
            'columns' => $columns,
            'columnsBy' => $this->columnsBy(
                is_array($data['interpreterConfig'] ?? null) ? $data['interpreterConfig'] : []
            )->value,
            'unusable' => $unusable?->value,
        ];
    }

    /**
     * @param array<mixed> $strategy
     *
     * @return array{by: 'id'|'field', field: string|null, language: string|null, column: string}|null
     */
    private function match(array $strategy): ?array
    {
        $by = self::LOADING_BY[$strategy['type'] ?? ''] ?? null;
        $settings = is_array($strategy['settings'] ?? null) ? $strategy['settings'] : [];
        $column = $this->text($settings['dataSourceIndex'] ?? null);
        $field = $this->text($settings['attributeName'] ?? null);

        if ($by === null || $column === null || ($by === 'field' && $field === null)) {
            return null;
        }

        // a field match within a tree path (as the attribute strategy reads it) finds only the objects below it
        $path = trim($this->text($settings['path'] ?? null) ?? '');
        if ($by === 'field' && $path !== '' && $path !== '/') {
            return null;
        }

        return [
            'by' => $by,
            'field' => $by === 'field' ? $field : null,
            'language' => $by === 'field' ? $this->text($settings['language'] ?? null) : null,
            'column' => $column,
        ];
    }

    /**
     * Whether the ad hoc matcher accepts the field and language, read-only: a removed or unsupported field, or a
     * localized one without a valid language, would fail only once the import starts.
     *
     * @param array{by: 'id'|'field', field: string|null, language: string|null, column: string} $match
     */
    private function matchable(ClassDefinition $class, array $match): bool
    {
        try {
            $this->rowMatcherFactory->assertMatchable($class, $match['field'], $match['language'] ?? '');
        } catch (InvalidConfigurationException) {
            return false;
        }

        return true;
    }

    /**
     * @param array<mixed> $strategy
     */
    private function folder(array $strategy): ?string
    {
        if (($strategy['type'] ?? null) !== self::STATIC_PATH) {
            return null;
        }

        return $this->text(is_array($strategy['settings'] ?? null) ? ($strategy['settings']['path'] ?? null) : null);
    }

    /**
     * How the configuration's rows key their columns: a CSV by the header row's names only when it keeps them, an XLSX
     * always by position; other sources by their field names.
     *
     * @param array<mixed> $interpreter
     */
    private function columnsBy(array $interpreter): ColumnKeying
    {
        $settings = is_array($interpreter['settings'] ?? null) ? $interpreter['settings'] : [];

        return match ($interpreter['type'] ?? null) {
            'xlsx' => ColumnKeying::Position,
            // read as the CSV interpreter's bool properties read them
            'csv' => (bool)($settings['skipFirstRow'] ?? false) && (bool)($settings['saveHeaderName'] ?? false)
                ? ColumnKeying::Name
                : ColumnKeying::Position,
            default => ColumnKeying::Name,
        };
    }

    private function folderUnusable(string $path, User $user): ?ConfigurationUnusableReason
    {
        // the ad hoc import places new objects only in a folder (as the upload checks), never below another object
        $folder = $this->dataObjectResolver->getByPath($path);
        if (!$folder instanceof Folder) {
            return ConfigurationUnusableReason::FolderMissing;
        }

        return $folder->isAllowed('create', $user) && $folder->isAllowed('save', $user)
            ? null
            : ConfigurationUnusableReason::FolderNotAllowed;
    }

    /**
     * @param array<mixed> $mapping
     *
     * @return list<string>
     */
    private function mappedColumns(array $mapping): array
    {
        $columns = [];
        foreach ($mapping as $row) {
            $indexes = is_array($row) ? ($row['dataSourceIndex'] ?? []) : [];
            foreach (is_array($indexes) ? $indexes : [$indexes] as $index) {
                $column = $this->text($index);
                if ($column !== null) {
                    $columns[] = $column;
                }
            }
        }

        return array_values(array_unique($columns));
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) && (string)$value !== '' ? (string)$value : null;
    }
}
