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

use Pimcore\Bundle\DataImporterBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface PublishStrategyInterface extends SettingsAwareInterface
{
    /**
     * Set publish state of element based on input data. Just created defines if element was creating during current run.
     *
     * @param ElementInterface $element
     * @param bool $justCreated
     * @param array $inputData
     *
     * @return ElementInterface
     */
    public function updatePublishState(ElementInterface $element, bool $justCreated, array $inputData): ElementInterface;
}
