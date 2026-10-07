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

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class StaticPathStrategy implements LocationStrategyInterface, SchemaAwareInterface
{
    private string $path;

    public function setSettings(array $settings): void
    {
        if (empty($settings['path'])) {
            throw new InvalidConfigurationException('Empty path.');
        }

        $this->path = $settings['path'];
    }

    public function updateParent(ElementInterface $element, array $inputData): ElementInterface
    {
        $element->setParent(Service::createFolderByPath($this->path));

        return $element;
    }

    public function getSchemaDescription(): string
    {
        return 'Use a static path for all elements';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('path')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Static path where elements will be created (e.g., /import/products)')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
