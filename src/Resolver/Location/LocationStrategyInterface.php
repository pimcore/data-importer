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
