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

namespace Pimcore\Bundle\DataHubBatchImportBundle\Cleanup;

use Pimcore\Model\Element\ElementInterface;

interface CleanupStrategyInterface
{
    /**
     * Apply cleanup on given element
     *
     * @param ElementInterface $element
     */
    public function doCleanup(ElementInterface $element): void;
}
