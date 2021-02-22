<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Location;

use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;
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
