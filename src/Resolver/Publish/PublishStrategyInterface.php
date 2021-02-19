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

namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Publish;

use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface PublishStrategyInterface extends SettingsAwareInterface
{
    /**
     * @param ElementInterface $element
     * @param bool $justCreated
     * @param array $inputData
     *
     * @return ElementInterface
     */
    public function updatePublishState(ElementInterface $element, bool $justCreated, array $inputData): ElementInterface;
}
