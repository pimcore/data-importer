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

use Pimcore\Model\Element\ElementInterface;

class NoChangeStrategy implements LocationStrategyInterface
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
