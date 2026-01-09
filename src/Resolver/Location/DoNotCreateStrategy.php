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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Location;

use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class DoNotCreateStrategy implements LocationStrategyInterface, SchemaAwareInterface
{
    public function updateParent(ElementInterface $element, array $inputData): ElementInterface
    {
        return $element;
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }

    public function getSchemaDescription(): string
    {
        return 'Prevents creation of new objects - only updates existing ones';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
