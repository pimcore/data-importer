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

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterConfigurationSaveParameters',
    title: 'Bundle Data Importer Configuration Save Parameters',
    required: ['configuration', 'modificationDate'],
    type: 'object'
)]
final readonly class ConfigurationSaveParameters
{
    public function __construct(
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
                'processingConfig' => [],
            ]
        )]
        private array $configuration,
        #[Property(
            description: 'Modification date timestamp for optimistic locking',
            type: 'integer',
            example: 1640000000
        )]
        private int $modificationDate,
    ) {
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getModificationDate(): int
    {
        return $this->modificationDate;
    }
}
