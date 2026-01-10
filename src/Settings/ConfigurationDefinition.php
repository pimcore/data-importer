<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Settings;

use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Defines the complete configuration structure for Data Importer
 *
 * This class provides the canonical definition of the configuration schema using
 * Symfony Config TreeBuilder. It's used by:
 * - ConfigurationSchemaService to generate JSON Schema for AI agents
 * - ConfigurationValidationService to validate configurations
 * - Any other service that needs to work with the configuration structure
 *
 * This ensures single source of truth for the configuration structure.
 */
class ConfigurationDefinition implements ConfigurationInterface
{
    private const INFO_STRATEGY_SETTINGS = 'Strategy-specific settings';
    protected ServiceLocator $dataLoaderLocator;

    protected ServiceLocator $interpreterLocator;

    protected ServiceLocator $loadStrategyLocator;

    protected ServiceLocator $locationStrategyLocator;

    protected ServiceLocator $publishStrategyLocator;

    protected ServiceLocator $operatorLocator;

    protected ServiceLocator $dataTargetLocator;

    protected ServiceLocator $cleanupStrategyLocator;

    public function __construct(
        ServiceLocator $dataLoaderLocator,
        ServiceLocator $interpreterLocator,
        ServiceLocator $loadStrategyLocator,
        ServiceLocator $locationStrategyLocator,
        ServiceLocator $publishStrategyLocator,
        ServiceLocator $operatorLocator,
        ServiceLocator $dataTargetLocator,
        ServiceLocator $cleanupStrategyLocator
    ) {
        $this->dataLoaderLocator = $dataLoaderLocator;
        $this->interpreterLocator = $interpreterLocator;
        $this->loadStrategyLocator = $loadStrategyLocator;
        $this->locationStrategyLocator = $locationStrategyLocator;
        $this->publishStrategyLocator = $publishStrategyLocator;
        $this->operatorLocator = $operatorLocator;
        $this->dataTargetLocator = $dataTargetLocator;
        $this->cleanupStrategyLocator = $cleanupStrategyLocator;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('configuration');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->getGeneralNode())
                ->append($this->getLoaderConfigNode())
                ->append($this->getInterpreterConfigNode())
                ->append($this->getResolverConfigNode())
                ->append($this->getProcessingConfigNode())
                ->append($this->getMappingConfigNode())
                ->append($this->getExecutionConfigNode())
            ->end();

        return $treeBuilder;
    }

    /**
     * Get TreeBuilder for general configuration section
     */
    public function getGeneralConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('general');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $this->applyGeneralConfigDefinition($node);

        return $builder;
    }

    /**
     * Get TreeBuilder for resolver configuration section
     */
    public function getResolverConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('resolverConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $this->applyResolverConfigDefinition($node);

        return $builder;
    }

    /**
     * Get TreeBuilder for processing configuration section
     */
    public function getProcessingConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('processingConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $this->applyProcessingConfigDefinition($node);

        return $builder;
    }

    /**
     * Get TreeBuilder for execution configuration section
     */
    public function getExecutionConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('executionConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $this->applyExecutionConfigDefinition($node);

        return $builder;
    }

    /**
     * Get TreeBuilder for loader configuration section
     */
    public function getLoaderConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('loaderConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $this->applyLoaderConfigDefinition($node);

        return $builder;
    }

    /**
     * Get TreeBuilder for interpreter configuration section
     */
    public function getInterpreterConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('interpreterConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $this->applyInterpreterConfigDefinition($node);

        return $builder;
    }

    /**
     * Get TreeBuilder for mapping configuration section
     */
    public function getMappingConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('mappingConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $this->applyMappingConfigDefinition($node);

        return $builder;
    }

    /**
     * Apply general configuration definition to a node
     */
    protected function applyGeneralConfigDefinition($node): void
    {
        $node
            ->isRequired()
            ->children()
                ->scalarNode('name')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Unique name of the configuration')
                ->end()
                ->booleanNode('active')
                    ->isRequired()
                    ->defaultTrue()
                    ->info('Whether the configuration is active')
                ->end()
                ->scalarNode('description')
                    ->defaultNull()
                    ->info('Optional description of the configuration')
                ->end()
                ->scalarNode('group')
                    ->defaultNull()
                    ->info('Optional group name for organizing configurations')
                ->end()
                ->scalarNode('path')
                    ->defaultNull()
                    ->info('Optional path for the configuration')
                ->end()
            ->end();
    }

    /**
     * Apply resolver configuration definition to a node
     */
    protected function applyResolverConfigDefinition($node): void
    {
        $node
            ->isRequired()
            /** @phpstan-ignore-next-line */
            ->children()
                ->enumNode('elementType')
                    ->isRequired()
                    ->values(['dataObject', 'asset'])
                    ->info('Type of element to create/update')
                ->end()
                ->scalarNode('dataObjectClassId')
                    ->defaultNull()
                    ->info('ID of the data object class (required when elementType is dataObject)')
                ->end()
                ->arrayNode('loadingStrategy')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->values(array_keys($this->loadStrategyLocator->getProvidedServices()))
                            ->info('Type of loading strategy')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('createLocationStrategy')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->values(array_keys($this->locationStrategyLocator->getProvidedServices()))
                            ->info('Type of location strategy for creating new elements')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('locationUpdateStrategy')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->values(array_keys($this->locationStrategyLocator->getProvidedServices()))
                            ->info('Type of location strategy for updating existing elements')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('publishingStrategy')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->values(array_keys($this->publishStrategyLocator->getProvidedServices()))
                            ->info('Type of publishing strategy')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Apply processing configuration definition to a node
     */
    protected function applyProcessingConfigDefinition($node): void
    {
        $node
            ->children()
                ->enumNode('executionType')
                    ->values([
                        ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
                        ImportProcessingService::EXECUTION_TYPE_PARALLEL
                    ])
                    ->defaultValue(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL)
                    ->info('How to execute the import (sequential or parallel)')
                ->end()
                ->scalarNode('idDataIndex')
                    ->defaultNull()
                    ->info('Data index field containing unique identifier for records')
                ->end()
                ->booleanNode('doDeltaCheck')
                    ->defaultFalse()
                    ->info('Whether to perform delta checks to skip unchanged records')
                ->end()
                ->booleanNode('doArchiveImportFile')
                    ->defaultFalse()
                    ->info('Whether to archive import files after processing')
                ->end()
                ->arrayNode('cleanup')
                    ->children()
                        ->booleanNode('doCleanup')
                            ->defaultFalse()
                            ->info('Whether to perform cleanup of elements not in import')
                        ->end()
                        ->enumNode('strategy')
                            ->defaultNull()
                            ->values(array_merge(
                                [null],
                                array_keys($this->cleanupStrategyLocator->getProvidedServices())
                            ))
                            ->info('Cleanup strategy to use')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info('Cleanup strategy-specific settings')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Apply execution configuration definition to a node
     */
    protected function applyExecutionConfigDefinition($node): void
    {
        $node
            ->children()
                ->enumNode('scheduleType')
                    ->values(['recurring', 'cron'])
                    ->defaultNull()
                    ->info('Type of scheduling (recurring or cron)')
                ->end()
                ->scalarNode('cronDefinition')
                    ->defaultNull()
                    ->info('Cron expression for scheduling (when scheduleType is cron)')
                ->end()
                ->scalarNode('scheduledAt')
                    ->defaultNull()
                    ->info('Timestamp or date for scheduled execution')
                ->end()
            ->end();
    }

    /**
     * Apply loader configuration definition to a node
     */
    protected function applyLoaderConfigDefinition($node): void
    {
        $node
            ->isRequired()
            ->children()
                ->enumNode('type')
                    ->isRequired()
                    ->values(array_keys($this->dataLoaderLocator->getProvidedServices()))
                    ->info('Type of data loader (e.g., asset, http, sftp, upload)')
                ->end()
                ->variableNode('settings')
                    ->defaultValue([])
                    ->info('Loader-specific settings (validated by loader service)')
                ->end()
            ->end();
    }

    /**
     * Apply interpreter configuration definition to a node
     */
    protected function applyInterpreterConfigDefinition($node): void
    {
        $node
            ->isRequired()
            ->children()
                ->enumNode('type')
                    ->isRequired()
                    ->values(array_keys($this->interpreterLocator->getProvidedServices()))
                    ->info('Type of data interpreter (e.g., csv, json, xml, xlsx)')
                ->end()
                ->variableNode('settings')
                    ->defaultValue([])
                    ->info('Interpreter-specific settings (validated by interpreter service)')
                ->end()
            ->end();
    }

    /**
     * Apply mapping configuration definition to a node
     */
    protected function applyMappingConfigDefinition($node): void
    {
        $node
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->arrayPrototype()
                ->children()
                    ->scalarNode('label')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->info('Label for this mapping')
                    ->end()
                    ->arrayNode('dataSourceIndex')
                        ->isRequired()
                        ->scalarPrototype()->end()
                        ->info('Array of source field names to map from')
                    ->end()
                    ->scalarNode('transformationResultType')
                        ->defaultNull()
                        ->info('Expected result type after transformations')
                    ->end()
                    ->arrayNode('transformationPipeline')
                        ->arrayPrototype()
                            ->children()
                                ->enumNode('type')
                                    ->isRequired()
                                    ->values(array_keys($this->operatorLocator->getProvidedServices()))
                                    ->info('Type of operator')
                                ->end()
                                ->variableNode('settings')
                                    ->defaultValue([])
                                    ->info('Operator-specific settings')
                                ->end()
                            ->end()
                        ->end()
                        ->info('Pipeline of transformations to apply')
                    ->end()
                    ->arrayNode('dataTarget')
                        ->isRequired()
                        ->children()
                            ->enumNode('type')
                                ->isRequired()
                                ->values(array_keys($this->dataTargetLocator->getProvidedServices()))
                                ->info('Type of data target')
                            ->end()
                            ->variableNode('settings')
                                ->defaultValue([])
                                ->info('Target-specific settings')
                            ->end()
                        ->end()
                        ->info('Configuration for where to write the data')
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Define general configuration node
     */
    protected function getGeneralNode()
    {
        $builder = new TreeBuilder('general');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        /** @phpstan-ignore-next-line */
        $node
            ->isRequired()
            ->children()
                ->scalarNode('name')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Unique name of the configuration')
                ->end()
                ->scalarNode('type')
                    ->isRequired()
                    ->defaultValue('dataImporterDataObject')
                    ->info('Configuration type')
                ->end()
                ->booleanNode('active')
                    ->isRequired()
                    ->defaultTrue()
                    ->info('Whether the configuration is active')
                ->end()
                ->scalarNode('description')
                    ->defaultNull()
                    ->info('Optional description of the configuration')
                ->end()
                ->scalarNode('group')
                    ->defaultNull()
                    ->info('Optional group name for organizing configurations')
                ->end()
                ->scalarNode('path')
                    ->defaultNull()
                    ->info('Optional path for the configuration')
                ->end()
            ->end();

        return $node;
    }

    /**
     * Define loader configuration node
     * Note: Settings are validated by the specific loader's SchemaAwareInterface
     */
    protected function getLoaderConfigNode()
    {
        $builder = new TreeBuilder('loaderConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        /** @phpstan-ignore-next-line */
        $node
            ->isRequired()
            ->children()
                /** @phpstan-ignore-next-line */
                ->enumNode('type')
                    ->isRequired()
                    ->values(array_keys($this->dataLoaderLocator->getProvidedServices()))
                    ->info('Type of data loader (e.g., asset, http, sftp, upload)')
                ->end()
                /** @phpstan-ignore-next-line */
                ->variableNode('settings')
                    ->defaultValue([])
                    ->info('Loader-specific settings (validated by loader service)')
                ->end()
            ->end();

        return $node;
    }

    /**
     * Define interpreter configuration node
     * Note: Settings are validated by the specific interpreter's SchemaAwareInterface
     */
    protected function getInterpreterConfigNode()
    {
        $builder = new TreeBuilder('interpreterConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        /** @phpstan-ignore-next-line */
        $node
            ->isRequired()
            ->children()
                /** @phpstan-ignore-next-line */
                ->enumNode('type')
                    ->isRequired()
                    ->values(array_keys($this->interpreterLocator->getProvidedServices()))
                    ->info('Type of data interpreter (e.g., csv, json, xml, xlsx)')
                ->end()
                /** @phpstan-ignore-next-line */
                ->variableNode('settings')
                    ->defaultValue([])
                    ->info('Interpreter-specific settings (validated by interpreter service)')
                ->end()
            ->end();

        return $node;
    }

    /**
     * Define resolver configuration node
     * Note: Strategy settings are validated by the specific strategy's SchemaAwareInterface
     */
    protected function getResolverConfigNode()
    {
        $builder = new TreeBuilder('resolverConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        /** @phpstan-ignore-next-line */
        $node
            ->isRequired()
            /** @phpstan-ignore-next-line */
            ->children()
                ->enumNode('elementType')
                    ->isRequired()
                    ->values(['dataObject', 'asset'])
                    ->info('Type of element to create/update')
                ->end()
                ->scalarNode('dataObjectClassId')
                    ->defaultNull()
                    ->info('ID of the data object class (required when elementType is dataObject)')
                ->end()
                ->arrayNode('loadingStrategy')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->values(array_keys($this->loadStrategyLocator->getProvidedServices()))
                            ->info('Type of loading strategy')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('createLocationStrategy')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->values(array_keys($this->locationStrategyLocator->getProvidedServices()))
                            ->info('Type of location strategy for creating new elements')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('locationUpdateStrategy')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->values(array_keys($this->locationStrategyLocator->getProvidedServices()))
                            ->info('Type of location strategy for updating existing elements')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('publishingStrategy')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->values(array_keys($this->publishStrategyLocator->getProvidedServices()))
                            ->info('Type of publishing strategy')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Define processing configuration node
     */
    protected function getProcessingConfigNode()
    {
        $builder = new TreeBuilder('processingConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        /** @phpstan-ignore-next-line */
        $node
            ->addDefaultsIfNotSet()
            ->children()
                /** @phpstan-ignore-next-line */
                ->enumNode('executionType')
                    ->values([
                        ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
                        ImportProcessingService::EXECUTION_TYPE_PARALLEL
                    ])
                    ->defaultValue(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL)
                    ->info('How to execute the import')
                ->end()
                ->scalarNode('idDataIndex')
                    ->defaultNull()
                    ->info('Data index field that contains unique identifier for records')
                ->end()
                ->booleanNode('doDeltaCheck')
                    ->defaultFalse()
                    ->info('Whether to perform delta checks to skip unchanged records')
                ->end()
                ->booleanNode('doArchiveImportFile')
                    ->defaultFalse()
                    ->info('Whether to archive import files after processing')
                ->end()
                ->arrayNode('cleanup')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('doCleanup')
                            ->defaultFalse()
                            ->info('Whether to perform cleanup')
                        ->end()
                        ->scalarNode('strategy')
                            ->defaultNull()
                            ->info('Cleanup strategy to use (e.g., delete, unpublish)')
                        ->end()
                        ->variableNode('settings')
                            ->defaultValue([])
                            ->info(self::INFO_STRATEGY_SETTINGS)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Define mapping configuration node
     * Note: Operators and data targets are validated by their respective SchemaAwareInterface implementations
     */
    protected function getMappingConfigNode()
    {
        $builder = new TreeBuilder('mappingConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        /** @phpstan-ignore-next-line */
        $node
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->arrayPrototype()
                ->children()
                    /** @phpstan-ignore-next-line */
                    ->scalarNode('label')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->info('Label for this mapping')
                    ->end()
                    /** @phpstan-ignore-next-line */
                    ->arrayNode('dataSourceIndex')
                        ->isRequired()
                        ->scalarPrototype()->end()
                        ->info('Array of source field names to map from')
                    ->end()
                    /** @phpstan-ignore-next-line */
                    ->scalarNode('transformationResultType')
                        ->defaultNull()
                        ->info('Expected result type after transformations')
                    ->end()
                    ->arrayNode('transformationPipeline')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('type')
                                    ->isRequired()
                                    ->cannotBeEmpty()
                                    ->info('Type of operator')
                                ->end()
                                ->variableNode('settings')
                                    ->defaultValue([])
                                    ->info('Operator-specific settings')
                                ->end()
                            ->end()
                        ->end()
                        ->info('Pipeline of transformations to apply')
                    ->end()
                    ->arrayNode('dataTarget')
                        ->isRequired()
                        ->children()
                            ->scalarNode('type')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('Type of data target')
                            ->end()
                            ->variableNode('settings')
                                ->defaultValue([])
                                ->info('Target-specific settings')
                            ->end()
                        ->end()
                        ->info('Configuration for where to write the data')
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Define execution configuration node
     */
    protected function getExecutionConfigNode()
    {
        $builder = new TreeBuilder('executionConfig');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        /** @phpstan-ignore-next-line */
        $node
            ->addDefaultsIfNotSet()
            ->children()
                /** @phpstan-ignore-next-line */
                ->enumNode('scheduleType')
                    ->values(['recurring', 'cron'])
                    ->defaultNull()
                    ->info('Type of scheduling')
                ->end()
                ->scalarNode('cronDefinition')
                    ->defaultNull()
                    ->info('Cron expression for scheduling (when scheduleType is cron)')
                ->end()
                ->scalarNode('scheduledAt')
                    ->defaultNull()
                    ->info('Timestamp or date for scheduled execution')
                ->end()
            ->end();

        return $node;
    }
}
