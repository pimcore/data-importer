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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Factory;

use Pimcore\Model\Element\ElementInterface;

interface FactoryInterface
{
    /**
     * Set subtype of element
     *
     * @param string $subType
     */
    public function setSubType(string $subType): void;

    /**
     * Create new element
     *
     * @return ElementInterface
     */
    public function createNewElement(): ElementInterface;
}
