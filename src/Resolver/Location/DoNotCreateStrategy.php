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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Location;

use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class DoNotCreateStrategy implements LocationStrategyInterface
{
    public function updateParent(ElementInterface $element, array $inputData): ElementInterface
    {
        return $element;
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }
}
