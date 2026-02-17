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

namespace Pimcore\Bundle\DataImporterBundle\Schema;

use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\StudioBackendBundle\Util\Schema\AdditionalAttributesInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Trait\AdditionalAttributesTrait;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterConfigurationDetail',
    title: 'Bundle Data Importer Configuration Detail',
    required: ['name', 'configuration', 'userPermissions', 'modificationDate'],
    type: 'object'
)]
final class ConfigurationDetail implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(description: 'Configuration name', type: 'string', example: 'my-import-config')]
        private readonly string $name,
        #[Property(
            description: 'Configuration data',
            properties: [
                new Property(property: 'general', type: 'object'),
                new Property(property: 'loaderConfig', type: 'object'),
                new Property(property: 'interpreterConfig', type: 'object'),
                new Property(property: 'resolverConfig', type: 'object'),
                new Property(property: 'processingConfig', type: 'object'),
                new Property(property: 'mappingConfig', type: 'object'),
                new Property(property: 'executionConfig', type: 'object'),
            ],
            type: 'object',
            example: [
                'general' => ['name' => 'my-import-config', 'active' => true],
                'loaderConfig' => ['type' => 'asset'],
                'interpreterConfig' => ['type' => 'csv'],
                'mappingConfig' => [],
                'executionConfig' => [],
                'resolverConfig' => [],
                'processingConfig' => []
            ]
        )]
        private readonly array $configuration,
        #[Property(
            description: 'User permissions',
            properties: [
                new Property(property: 'update', type: 'boolean', example: true),
                new Property(property: 'delete', type: 'boolean', example: true),
            ],
            type: 'object'
        )]
        private readonly array $userPermissions,
        #[Property(description: 'Modification date timestamp', type: 'integer', example: 1640000000)]
        private readonly int $modificationDate,
        #[Property(
            description: 'Available column headers from preview data',
            type: 'array',
            items: new Items(type: 'string'),
            example: ['id', 'name', 'sku', 'price']
        )]
        private readonly array $columnHeaders = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getUserPermissions(): array
    {
        return $this->userPermissions;
    }

    public function getModificationDate(): int
    {
        return $this->modificationDate;
    }

    public function getColumnHeaders(): array
    {
        return $this->columnHeaders;
    }
}
