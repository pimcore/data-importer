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

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterTransformationResultParameters',
    title: 'Bundle Data Importer Transformation Result Parameters',
    required: ['mappingConfig', 'dataRow'],
    type: 'object'
)]
final readonly class TransformationResultParameters
{
    public function __construct(
        #[Property(
            description: 'Mapping configuration entries to preview',
            type: 'array',
            items: new Items(type: 'object', additionalProperties: true)
        )]
        private array $mappingConfig = [],
        #[Property(
            description: 'The source record to transform: column => value',
            type: 'object',
            example: ['sku' => 'A-100'],
            additionalProperties: true
        )]
        private array $dataRow = [],
    ) {
    }

    public function getMappingConfig(): array
    {
        return array_values(array_filter($this->mappingConfig, 'is_array'));
    }

    public function getDataRow(): array
    {
        return $this->dataRow;
    }
}
