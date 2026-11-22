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
    schema: 'BundleDataImporterAdHocImportRunObjectsResponse',
    title: 'Bundle Data Importer Ad Hoc Import Run Objects Response',
    required: ['totalItems', 'fields', 'items'],
    type: 'object'
)]
final readonly class AdHocImportRunObjectsResponse
{
    /**
     * @param list<array{key: string, label: string}> $fields
     * @param list<array{id: int, fullPath: string, modificationDate: int|null, values: list<string|null>}> $items
     */
    public function __construct(
        #[Property(description: 'The objects of the kind the run saved', type: 'integer', example: 298)]
        private int $totalItems,
        #[Property(
            description: 'The fields the file wrote, in the order of the values',
            type: 'array',
            items: new Items(
                required: ['key', 'label'],
                properties: [
                    new Property(property: 'key', type: 'string', example: 'name'),
                    new Property(property: 'label', type: 'string', example: 'Name (en)'),
                ],
                type: 'object'
            )
        )]
        private array $fields,
        #[Property(
            description: 'The objects on the page; one deleted since or no longer visible to the user is left out',
            type: 'array',
            items: new Items(
                required: ['id', 'fullPath', 'modificationDate', 'values'],
                properties: [
                    new Property(property: 'id', type: 'integer', example: 1042),
                    new Property(property: 'fullPath', type: 'string', example: '/Products/Outdoor/NH-1001'),
                    new Property(
                        property: 'modificationDate',
                        description: 'As a timestamp',
                        type: 'integer',
                        nullable: true
                    ),
                    new Property(
                        property: 'values',
                        description: 'Per field, its value as the object holds it now',
                        type: 'array',
                        items: new Items(type: 'string', nullable: true)
                    ),
                ],
                type: 'object'
            )
        )]
        private array $items,
    ) {
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return list<array{id: int, fullPath: string, modificationDate: int|null, values: list<string|null>}>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
