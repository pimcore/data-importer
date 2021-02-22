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

namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Factory;

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
