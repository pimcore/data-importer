<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Publish;

use Pimcore\Model\Element\ElementInterface;

class NoChangePublishNewStrategy implements PublishStrategyInterface
{
    public function setSettings(array $settings): void
    {
        //nothing to do
    }

    public function updatePublishState(ElementInterface $element, bool $justCreated, array $inputData): ElementInterface
    {
        if ($justCreated && method_exists($element, 'setPublished')) {
            $element->setPublished(true);
        }

        return $element;
    }
}
