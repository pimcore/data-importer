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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Load;

use Doctrine\DBAL\Connection;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
abstract class AbstractLoad implements LoadStrategyInterface
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var mixed
     */
    protected $dataSourceIndex;

    /**
     * @var string
     */
    protected $dataObjectClassId;

    /**
     * AbstractLoad constructor.
     *
     * @param Connection $connection
     * @param DataObjectLoader $dataObjectLoader
     */
    public function __construct(Connection $connection, protected DataObjectLoader $dataObjectLoader)
    {
        $this->db = $connection;
    }

    public function setSettings(array $settings): void
    {
        if (!array_key_exists('dataSourceIndex', $settings) || $settings['dataSourceIndex'] === null) {
            throw new InvalidConfigurationException('Empty data source index.');
        }

        $this->dataSourceIndex = $settings['dataSourceIndex'];
    }

    /**
     * @param string $dataObjectClassId
     */
    public function setDataObjectClassId($dataObjectClassId): void
    {
        $this->dataObjectClassId = $dataObjectClassId;
    }

    /**
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    protected function getClassName()
    {
        $class = ClassDefinition::getById($this->dataObjectClassId);
        if (empty($class)) {
            throw new InvalidConfigurationException("Class `{$this->dataObjectClassId}` not found.");
        }

        return '\\Pimcore\\Model\\DataObject\\' . ucfirst($class->getName());
    }

    /**
     * @param array $inputData
     *
     * @return ElementInterface|null
     *
     * @throws \InvalidArgumentException
     */
    public function loadElement(array $inputData): ?ElementInterface
    {
        $identifier = $this->extractIdentifierFromData($inputData);
        if ($identifier === null) {
            return null;
        }

        return $this->loadElementByIdentifier($identifier);
    }

    /**
     * @param array $inputData
     *
     * @return mixed
     */
    public function extractIdentifierFromData(array $inputData)
    {
        if (!array_key_exists($this->dataSourceIndex, $inputData)) {
            throw new \InvalidArgumentException('Identifier not set.');
        }

        return $inputData[$this->dataSourceIndex];
    }

    /**
     * Creates base TreeBuilder with common configuration options.
     * Child classes should call this and add their specific settings.
     */
    protected function getBaseConfigTreeBuilder(): TreeBuilder
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
                    ->info(
                        'Index/key of the data source field containing the identifier ' .
                        'to match against'
                    )
                ->end()
            ->end();

        return $treeBuilder;
    }
}
