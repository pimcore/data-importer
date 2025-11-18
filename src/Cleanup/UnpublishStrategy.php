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

class UnpublishStrategy implements CleanupStrategyInterface
{
    public function doCleanup(ElementInterface $element): bool
    {
        if (!method_exists($element, 'setPublished') || !method_exists($element, 'isPublished') ||
            !$element->isPublished()) {
            return false;
        }
        $element->setPublished(false);
        $element->save();
        return true;
    }
}
