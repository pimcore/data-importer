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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Bundle\DataImporterBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface DataTargetInterface extends SettingsAwareInterface
{
    /**
     * Assign given data to element
     *
     * @param ElementInterface $element
     * @param mixed $data
     *
     * @return mixed
     */
    public function assignData(ElementInterface $element, $data);
}
