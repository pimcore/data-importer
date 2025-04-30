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
