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

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class AttributeBasedStrategy implements PublishStrategyInterface
{
    private mixed $dataSourceIndex;

    public function setSettings(array $settings): void
    {
        if (($dsi = $settings['dataSourceIndex'] ?? null) === null) {
            throw new InvalidConfigurationException('Empty data source index.');
        }

        $this->dataSourceIndex = $dsi;
    }

    public function updatePublishState(ElementInterface $element, bool $justCreated, array $inputData): ElementInterface
    {
        if (method_exists($element, 'setPublished')) {
            $element->setPublished($inputData[$this->dataSourceIndex] ?? false);
        }

        return $element;
    }
}
