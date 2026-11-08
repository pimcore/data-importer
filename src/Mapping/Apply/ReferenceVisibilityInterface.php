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
 * A reference lookup that also limits what the mapping may read: an element it does not accept counts as not found,
 * however the operator loaded it.
 */
interface ReferenceVisibilityInterface extends ReferenceLookupInterface
{
    public function isVisible(ElementInterface $element): bool;
}
