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
    schema: 'BundleDataImporterLoadPreviewParameters',
    title: 'Bundle Data Importer Load Preview Parameters',
    type: 'object'
)]
final readonly class LoadPreviewParameters
{
    public function __construct(
        #[Property(
            description: 'Optional unsaved in-progress configuration from the UI. When provided, the interpreter and mapping use these settings instead of the saved configuration.',
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
            nullable: true
        )]
        private ?array $currentConfig = null,
        #[Property(
            description: 'Zero-based record number to preview from the data source',
            type: 'integer',
            example: 0
        )]
        private int $recordNumber = 0,
    ) {
    }

    public function getCurrentConfig(): ?array
    {
        return $this->currentConfig;
    }

    public function getRecordNumber(): int
    {
        return $this->recordNumber;
    }
}
