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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Apply;

use Pimcore\Model\Element\ElementInterface;

/**
 * Resolves references of the `loadDataObject` and `loadAsset` operators while a mapping is applied without saving.
 */
interface ReferenceLookupInterface
{
    /**
     * Returns the referenced element, or null to let the operator fall back to its own load strategy.
     *
     * The element is passed on unchanged, so it may be unsaved. It must be a data object for
     * ReferenceType::DataObject and an asset for ReferenceType::Asset.
     */
    public function find(ReferenceQuery $query): ?ElementInterface;
}
