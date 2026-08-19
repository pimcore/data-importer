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
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class AttributeBasedStrategy implements PublishStrategyInterface, SchemaAwareInterface
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

    public function getSchemaDescription(): string
    {
        return 'Sets publish state based on a boolean value from input data';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('dataSourceIndex')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Index in input data array containing the publish state (boolean)')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
