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

use Pimcore\Model\Element\ElementInterface;

class NoChangeUnpublishNewStrategy implements PublishStrategyInterface
{
    public function setSettings(array $settings): void
    {
        //nothing to do
    }

    public function updatePublishState(ElementInterface $element, bool $justCreated, array $inputData): ElementInterface
    {
        if ($justCreated) {
            $element->setPublished(false);
        }

        return $element;
    }
}
