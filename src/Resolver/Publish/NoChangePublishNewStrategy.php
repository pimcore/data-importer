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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Publish;

use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class NoChangePublishNewStrategy implements PublishStrategyInterface
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
