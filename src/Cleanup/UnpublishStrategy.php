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
    public function doCleanup(ElementInterface $element): void
    {
        if (method_exists($element, 'setPublished')) {
            $element->setPublished(false);
            $element->save();
        }
    }
}
