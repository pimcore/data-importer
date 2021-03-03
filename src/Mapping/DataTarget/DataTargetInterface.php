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
