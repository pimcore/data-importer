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
 * One row's value at one point of a column's transformations.
 *
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportTransformationCell',
    title: 'Bundle Data Importer Ad Hoc Import Transformation Cell',
    required: ['items', 'failed'],
    type: 'object'
)]
final readonly class AdHocImportTransformationCell
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        #[Property(
            description: 'The value as text, one item per entry of a list; none for an empty value',
            type: 'array',
            items: new Items(type: 'string', example: 'Chair')
        )]
        private array $items = [],
        #[Property(description: 'Whether a step failed on this row\'s value', type: 'boolean', example: false)]
        private bool $failed = false,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getFailed(): bool
    {
        return $this->failed;
    }
}
