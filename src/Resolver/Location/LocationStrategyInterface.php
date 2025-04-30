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

use Pimcore\Bundle\DataImporterBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface LocationStrategyInterface extends SettingsAwareInterface
{
    /**
     * Update parent of given element based on input data
     *
     * @param ElementInterface $element
     * @param array $inputData
     *
     * @return ElementInterface
     */
    public function updateParent(ElementInterface $element, array $inputData): ElementInterface;
}
