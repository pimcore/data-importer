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
    schema: 'BundleDataImporterCalculateTransformationResultTypeParameters',
    title: 'Bundle Data Importer Calculate Transformation Result Type Parameters',
    required: ['currentConfig'],
    type: 'object'
)]
final readonly class CalculateTransformationResultTypeParameters
{
    public function __construct(
        #[Property(
            description: 'A single mapping configuration entry to evaluate the transformation result type for',
            properties: [
                new Property(property: 'label', description: 'Mapping label', type: 'string', example: 'SKU'),
                new Property(
                    property: 'dataSourceIndex',
                    description: 'Data source column indices',
                    type: 'array',
                    items: new Items(type: 'string'),
                    example: ['0']
                ),
                new Property(property: 'transformationPipeline', type: 'array', items: new Items(type: 'object')),
                new Property(property: 'dataTarget', type: 'object'),
            ],
            type: 'object'
        )]
        private array $currentConfig,
    ) {
    }

    public function getCurrentConfig(): array
    {
        return $this->currentConfig;
    }
}
