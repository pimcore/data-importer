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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Type;

/**
 * How a key listing is ordered, paged and narrowed - everything about
 * {@see ClassificationStoreDataTypeService::listClassificationStoreKeyList()} except which store it
 * reads and which result type the keys have to be able to hold.
 *
 * @internal
 */
final readonly class ClassificationStoreKeyListOptions
{
    /**
     * @param string|null $searchString matched against the key name, the group name and the key description
     * @param string|null $filterString the grid filter, as the JSON the key picker sends
     * @param bool $loadAdvancedRelations whether a relation result type also offers the advanced relation key types
     */
    public function __construct(
        public string $orderKey = 'name',
        public string $order = 'ASC',
        public int $start = 0,
        public int $limit = 15,
        public ?string $searchString = null,
        public ?string $filterString = null,
        public bool $loadAdvancedRelations = false
    ) {
    }
}
