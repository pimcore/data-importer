<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Cleanup;

use Pimcore\Model\Element\ElementInterface;

/**
 * Optional for cleanup strategies: tells whether doCleanup() would change the element.
 * The import skips the cleanup and its log entry for elements that need none.
 */
interface ConditionalCleanupStrategyInterface extends CleanupStrategyInterface
{
    public function needsCleanup(ElementInterface $element): bool;
}
