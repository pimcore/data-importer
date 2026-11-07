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
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportConfigurationsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;

/**
 * @phpstan-import-type ImportConfiguration from AdHocImportConfigurationsResponse
 *
 * @internal
 */
final readonly class AdHocImportConfigurationService implements AdHocImportConfigurationServiceInterface
{
    use CurrentUserResolverTrait;

    public const string UNUSABLE_CLASS = 'class';

    public const string UNUSABLE_MATCHING = 'matching';

    public const string UNUSABLE_FOLDER = 'folder';

    public const string UNUSABLE_FOLDER_MISSING = 'folder-missing';

    public const string UNUSABLE_FOLDER_NOT_ALLOWED = 'folder-not-allowed';

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
        ?Closure $listConfigurations = null,
    ) {
        $this->listConfigurations = $listConfigurations ?? static fn (): array => Configuration::getList();
    }

    public function listConfigurations(): AdHocImportConfigurationsResponse
    {
        $user = $this->resolveCurrentUser();

        $creatable = [];
        foreach ($this->classDefinitionService->getClassDefinitionCollection(creatableOnly: true) as $class) {
            $creatable[$class->getId()] = true;
        }

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

        $unusable = match (true) {
            $classId === null || !isset($creatable[$classId]) => self::UNUSABLE_CLASS,
            $match === null => self::UNUSABLE_MATCHING,
            $folder === null => self::UNUSABLE_FOLDER,
            default => $this->folderUnusable($folder, $user),
        };

        $columns = $this->mappedColumns(is_array($data['mappingConfig'] ?? null) ? $data['mappingConfig'] : []);
        if ($match !== null) {
            $columns = array_values(array_unique([$match['column'], ...$columns]));
        }

        return [
            'name' => (string)$configuration->getName(),
            'classId' => $classId,
            'className' => $classId === null ? null : $this->classDefinitionResolver->getById($classId)?->getName(),
            'matchBy' => $match['by'] ?? null,
            'matchField' => $match['field'] ?? null,
            'matchLanguage' => $match['language'] ?? null,
            'matchColumn' => $match['column'] ?? null,
            'newObjectsFolder' => $folder,
            'columns' => $columns,
            'unusable' => $unusable,
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

        return [
            'by' => $by,
            'field' => $by === 'field' ? $field : null,
            'language' => $by === 'field' ? $this->text($settings['language'] ?? null) : null,
            'column' => $column,
        ];
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

    private function folderUnusable(string $path, User $user): ?string
    {
        $folder = $this->dataObjectResolver->getByPath($path);
        if ($folder === null) {
            return self::UNUSABLE_FOLDER_MISSING;
        }

        return $folder->isAllowed('create', $user) ? null : self::UNUSABLE_FOLDER_NOT_ALLOWED;
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
