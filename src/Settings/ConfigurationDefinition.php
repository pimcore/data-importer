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

    private const INFO_EMPTY_OBJECT_SUFFIX =
        ' - if no specific settings needed, use empty object {}';

    private const INFO_EMPTY_OBJECT =
        'if no specific settings needed, use empty object {}';

    private const INFO_OPTIONAL_OMIT = 'optional, omit if not needed';

    private const INFO_DEFAULT_FALSE = 'if not specified, use false';

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
                ->scalarNode('type')
                    ->isRequired()
                    ->validate()
                        ->ifNotInArray(['dataImporterDataObject'])
                        ->thenInvalid('Type must be "dataImporterDataObject"')
                    ->end()
                    ->info('Configuration type - must be "dataImporterDataObject"')
                ->end()
                ->booleanNode('active')
                    ->isRequired()
                    ->info('Whether the configuration is active')
                ->end()
                ->scalarNode('description')
                    ->info('Optional description of the configuration')
                ->end()
                ->scalarNode('group')
                    ->info('Optional group name for organizing configurations')
                ->end()
                ->scalarNode('path')
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
                    ->info(
                        'ID of the data object class ' .
                        '(required when elementType is dataObject)'
                    )
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
                            ->info(
                                self::INFO_STRATEGY_SETTINGS .
                                self::INFO_EMPTY_OBJECT_SUFFIX
                            )
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
                            ->info(
                                self::INFO_STRATEGY_SETTINGS .
                                self::INFO_EMPTY_OBJECT_SUFFIX
                            )
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
                            ->info(
                                self::INFO_STRATEGY_SETTINGS .
                                self::INFO_EMPTY_OBJECT_SUFFIX
                            )
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
                            ->info(
                                self::INFO_STRATEGY_SETTINGS .
                                self::INFO_EMPTY_OBJECT_SUFFIX
                            )
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
                    ->info(
                        'How to execute the import (sequential or parallel) - ' .
                        'if not specified, use "sequential"'
                    )
                ->end()
                ->scalarNode('idDataIndex')
                    ->info(
                        'Data index field containing unique identifier for records - ' .
                        self::INFO_OPTIONAL_OMIT
                    )
                ->end()
                ->booleanNode('doDeltaCheck')
                    ->info(
                        'Whether to perform delta checks to skip unchanged records - ' .
                        self::INFO_DEFAULT_FALSE
                    )
                ->end()
                ->booleanNode('doArchiveImportFile')
                    ->info(
                        'Whether to archive import files after processing - ' .
                        self::INFO_DEFAULT_FALSE
                    )
                ->end()
                ->booleanNode('disableVersioning')
                    ->info(
                        'Whether to disable object versioning during import - ' .
                        self::INFO_DEFAULT_FALSE
                    )
                ->end()
                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('disableInfoLogs')
                            ->defaultFalse()
                            ->info('Whether to suppress info level import logs')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cleanup')
                    ->children()
                        ->booleanNode('doCleanup')
                            ->info(
                                'Whether to perform cleanup of elements not in import - ' .
                                self::INFO_DEFAULT_FALSE
                            )
                        ->end()
                        ->enumNode('strategy')
                            ->values(array_merge(
                                [null],
                                array_keys($this->cleanupStrategyLocator->getProvidedServices())
                            ))
                            ->info(
                                'Cleanup strategy to use, required when doCleanup is true, ' .
                                'defaults to unpublish'
                            )
                        ->end()
                        ->variableNode('settings')
                            ->info(
                                'Cleanup strategy-specific settings - ' .
                                self::INFO_EMPTY_OBJECT
                            )
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
                    ->info(
                        'Type of scheduling (recurring or cron) - ' .
                        self::INFO_OPTIONAL_OMIT
                    )
                ->end()
                ->scalarNode('cronDefinition')
                    ->info(
                        'Cron expression for scheduling (when scheduleType is cron) - ' .
                        self::INFO_OPTIONAL_OMIT
                    )
                ->end()
                ->scalarNode('scheduledAt')
                    ->info(
                        'Timestamp or date for scheduled execution - ' .
                        self::INFO_OPTIONAL_OMIT
                    )
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
                    ->info(
                        'Loader-specific settings (validated by loader service) - ' .
                        self::INFO_EMPTY_OBJECT
                    )
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
                    ->info(
                        'Interpreter-specific settings (validated by interpreter service) - ' .
                        self::INFO_EMPTY_OBJECT
                    )
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
                        ->info('Array of source field names to map from. for csv files it is the number ' .
                                'of the column starting from 0, for json/xml it is field name.')
                    ->end()
                    ->variableNode('settings')
                        ->info('Legacy per-item settings. Ignored by the importer '
                            . '(MappingConfigurationFactory reads settings only inside '
                            . 'transformationPipeline entries and dataTarget), but present in '
                            . 'configurations written by older versions, so it is accepted.')
                    ->end()
                    ->scalarNode('transformationResultType')
                        ->cannotBeEmpty()
                        ->info('Result type of the transformation pipeline, used to check ' .
                            'compatibility with the data target. Defaults to `default`. ' .
                            'Use the enrich_import_config tool to calculate it.')
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
                                    ->info(
                                        'Operator-specific settings - ' .
                                        self::INFO_EMPTY_OBJECT
                                    )
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
                                ->info(
                                    'Target-specific settings - ' .
                                    self::INFO_EMPTY_OBJECT
                                )
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

        $this->applyGeneralConfigDefinition($node);

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

        $this->applyLoaderConfigDefinition($node);

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

        $this->applyInterpreterConfigDefinition($node);

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

        $this->applyResolverConfigDefinition($node);

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

        $this->applyProcessingConfigDefinition($node);

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

        $this->applyMappingConfigDefinition($node);

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

        $this->applyExecutionConfigDefinition($node);

        return $node;
    }
}
