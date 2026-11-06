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
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;

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
        private array $mappingConfig,
        #[Property(
            description: 'The source record to transform: column => value',
            type: 'object',
            example: ['sku' => 'A-100'],
            additionalProperties: true
        )]
        private array $dataRow,
    ) {
    }

    /**
     * @throws InvalidArgumentException for an entry that is not an object: dropping it would shift the preview positions
     */
    public function getMappingConfig(): array
    {
        foreach ($this->mappingConfig as $index => $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException(sprintf('Mapping entry %s is not an object.', $index));
            }
        }

        return array_values($this->mappingConfig);
    }

    public function getDataRow(): array
    {
        return $this->dataRow;
    }
}
