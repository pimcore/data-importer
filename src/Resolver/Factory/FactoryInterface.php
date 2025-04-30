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
