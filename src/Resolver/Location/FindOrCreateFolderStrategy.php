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
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class FindOrCreateFolderStrategy implements LocationStrategyInterface, SchemaAwareInterface
{
    private mixed $dataSourceIndex;

    private ?string $fallbackPath = null;

    public function __construct(private readonly DataObjectLoader $dataObjectLoader)
    {
    }

    public function setSettings(array $settings): void
    {
        if (
            $settings['dataSourceIndex'] !== 0 &&
            $settings['dataSourceIndex'] !== '0' &&
            empty($settings['dataSourceIndex'])
        ) {
            throw new InvalidConfigurationException('Empty data source index.');
        }

        $this->dataSourceIndex = $settings['dataSourceIndex'];

        $this->fallbackPath = $settings['fallbackPath'] ?? null;
    }

    public function updateParent(ElementInterface $element, array $inputData): ElementInterface
    {
        $newParent = null;
        $identifier = $inputData[$this->dataSourceIndex] ?? null;

        if ($identifier) {
            $newParent = $this->dataObjectLoader->loadByPath($identifier);

            if (!($newParent instanceof DataObject)) {
                $newParent = Service::createFolderByPath($identifier);
            }
        }

        if (!($newParent instanceof DataObject) && $this->fallbackPath) {
            $newParent = DataObject::getByPath($this->fallbackPath);
        }

        if (!($newParent)) {
            $newParent = DataObject::getById(1);
        }

        return $element->setParent($newParent);
    }

    public function getSchemaDescription(): string
    {
        return 'Finds existing folder by path or creates it if it does not exist';
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
                    ->info('Index in input data array containing the folder path')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('fallbackPath')
                    ->info('Fallback path if folder path is not provided in data')
                    ->defaultValue(null)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
