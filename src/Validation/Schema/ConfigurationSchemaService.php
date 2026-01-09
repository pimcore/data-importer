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

namespace Pimcore\Bundle\DataImporterBundle\Validation\Schema;

use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationDefinition;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Service that provides schema information about all configuration options
 * for AI agents and other automated tools
 *
 * This service introspects the registered services and provides metadata about:
 * - Available types for each configuration section
 * - Required and optional settings for each type
 * - Valid values and constraints
 * - Description of each configuration option
 */
class ConfigurationSchemaService
{
    protected ServiceLocator $dataLoaderLocator;

    protected ServiceLocator $interpreterLocator;

    protected ServiceLocator $loadStrategyLocator;

    protected ServiceLocator $locationStrategyLocator;

    protected ServiceLocator $publishStrategyLocator;

    protected ServiceLocator $operatorLocator;

    protected ServiceLocator $dataTargetLocator;

    protected ServiceLocator $cleanupStrategyLocator;

    protected TreeBuilderToJsonSchemaConverter $jsonSchemaConverter;

    protected ConfigurationDefinition $configDefinition;

    public function __construct(
        ConfigurationSchemaLocators $locators,
        TreeBuilderToJsonSchemaConverter $jsonSchemaConverter,
        ConfigurationDefinition $configDefinition
    ) {
        $this->dataLoaderLocator = $locators->dataLoader();
        $this->interpreterLocator = $locators->interpreter();
        $this->loadStrategyLocator = $locators->loadStrategy();
        $this->locationStrategyLocator = $locators->locationStrategy();
        $this->publishStrategyLocator = $locators->publishStrategy();
        $this->operatorLocator = $locators->operator();
        $this->dataTargetLocator = $locators->dataTarget();
        $this->cleanupStrategyLocator = $locators->cleanupStrategy();
        $this->jsonSchemaConverter = $jsonSchemaConverter;
        $this->configDefinition = $configDefinition;
    }

    /**
     * Get complete schema for the entire configuration
     */
    public function getCompleteSchema(): array
    {
        return [
            'general' => $this->getGeneralSchema(),
            'loaderConfig' => $this->getLoaderConfigSchema(),
            'interpreterConfig' => $this->getInterpreterConfigSchema(),
            'resolverConfig' => $this->getResolverConfigSchema(),
            'processingConfig' => $this->getProcessingConfigSchema(),
            'mappingConfig' => $this->getMappingConfigSchema(),
            'executionConfig' => $this->getExecutionConfigSchema(),
        ];
    }

    /**
     * Get schema for general configuration
     */
    public function getGeneralSchema(): array
    {
        $treeBuilder = $this->configDefinition->getGeneralConfigTreeBuilder();

        return $this->jsonSchemaConverter->convert($treeBuilder);
    }

    /**
     * Get schema for loader configuration
     */
    public function getLoaderConfigSchema(): array
    {
        // Get base schema from ConfigurationDefinition
        $treeBuilder = $this->configDefinition->getLoaderConfigTreeBuilder();
        $baseSchema = $this->jsonSchemaConverter->convert($treeBuilder);

        // Enhance with available loader types from service locator
        $availableTypes = [];
        foreach ($this->dataLoaderLocator->getProvidedServices() as $type => $class) {
            $availableTypes[$type] = $this->getServiceSchema($type, $class, $this->dataLoaderLocator);
        }

        // Add available types to the schema
        $baseSchema['availableTypes'] = $availableTypes;
        if (isset($baseSchema['properties']['type']['enum'])) {
            $baseSchema['properties']['type']['enum'] = array_keys($availableTypes);
        }

        return $baseSchema;
    }

    /**
     * Get schema for interpreter configuration
     */
    public function getInterpreterConfigSchema(): array
    {
        // Get base schema from ConfigurationDefinition
        $treeBuilder = $this->configDefinition->getInterpreterConfigTreeBuilder();
        $baseSchema = $this->jsonSchemaConverter->convert($treeBuilder);

        // Enhance with available interpreter types from service locator
        $availableTypes = [];
        foreach ($this->interpreterLocator->getProvidedServices() as $type => $class) {
            $availableTypes[$type] = $this->getServiceSchema($type, $class, $this->interpreterLocator);
        }

        // Add available types to the schema
        $baseSchema['availableTypes'] = $availableTypes;
        if (isset($baseSchema['properties']['type']['enum'])) {
            $baseSchema['properties']['type']['enum'] = array_keys($availableTypes);
        }

        return $baseSchema;
    }

    /**
     * Get schema for resolver configuration
     */
    public function getResolverConfigSchema(): array
    {
        // Get base schema from ConfigurationDefinition
        $treeBuilder = $this->configDefinition->getResolverConfigTreeBuilder();
        $baseSchema = $this->jsonSchemaConverter->convert($treeBuilder);

        // Enhance with available strategy types from service locators
        $loadingStrategies = [];
        foreach ($this->loadStrategyLocator->getProvidedServices() as $type => $class) {
            $loadingStrategies[$type] = $this->getServiceSchema($type, $class, $this->loadStrategyLocator);
        }

        $locationStrategies = [];
        foreach ($this->locationStrategyLocator->getProvidedServices() as $type => $class) {
            $locationStrategies[$type] = $this->getServiceSchema($type, $class, $this->locationStrategyLocator);
        }

        $publishingStrategies = [];
        foreach ($this->publishStrategyLocator->getProvidedServices() as $type => $class) {
            $publishingStrategies[$type] = $this->getServiceSchema($type, $class, $this->publishStrategyLocator);
        }

        // Add available types to the schema
        if (isset($baseSchema['properties']['loadingStrategy'])) {
            $baseSchema['properties']['loadingStrategy']['availableTypes'] = $loadingStrategies;
        }
        if (isset($baseSchema['properties']['createLocationStrategy'])) {
            $baseSchema['properties']['createLocationStrategy']['availableTypes'] = $locationStrategies;
        }
        if (isset($baseSchema['properties']['locationUpdateStrategy'])) {
            $baseSchema['properties']['locationUpdateStrategy']['availableTypes'] = $locationStrategies;
        }
        if (isset($baseSchema['properties']['publishingStrategy'])) {
            $baseSchema['properties']['publishingStrategy']['availableTypes'] = $publishingStrategies;
        }

        return $baseSchema;
    }

    /**
     * Get schema for processing configuration
     */
    public function getProcessingConfigSchema(): array
    {
        // Get base schema from ConfigurationDefinition
        $treeBuilder = $this->configDefinition->getProcessingConfigTreeBuilder();
        $baseSchema = $this->jsonSchemaConverter->convert($treeBuilder);

        // Enhance with available cleanup strategies from service locator
        $cleanupStrategies = [];
        foreach ($this->cleanupStrategyLocator->getProvidedServices() as $type => $class) {
            $cleanupStrategies[$type] = $this->getServiceSchema($type, $class, $this->cleanupStrategyLocator);
        }

        // Add available strategies to the schema
        if (isset($baseSchema['properties']['cleanup'])) {
            $baseSchema['properties']['cleanup']['availableStrategies'] = $cleanupStrategies;
        }

        return $baseSchema;
    }

    /**
     * Get schema for mapping configuration
     */
    public function getMappingConfigSchema(): array
    {
        // Get base schema from ConfigurationDefinition
        $treeBuilder = $this->configDefinition->getMappingConfigTreeBuilder();
        $baseSchema = $this->jsonSchemaConverter->convert($treeBuilder);

        // Enhance with available operators and data targets from service locators
        $operators = [];
        foreach ($this->operatorLocator->getProvidedServices() as $type => $class) {
            $operators[$type] = $this->getServiceSchema($type, $class, $this->operatorLocator);
        }

        $dataTargets = [];
        foreach ($this->dataTargetLocator->getProvidedServices() as $type => $class) {
            $dataTargets[$type] = $this->getServiceSchema($type, $class, $this->dataTargetLocator);
        }

        // Add available types to the schema
        if (isset($baseSchema['items']['properties']['transformationPipeline'])) {
            $tp = &$baseSchema['items']['properties']['transformationPipeline'];
            $tp['availableOperators'] = $operators;
            if (isset($tp['items']['properties']['type']['enum'])) {
                $tp['items']['properties']['type']['enum'] = array_keys($operators);
            }
        }

        if (isset($baseSchema['items']['properties']['dataTarget'])) {
            $dt = &$baseSchema['items']['properties']['dataTarget'];
            $dt['availableTargets'] = $dataTargets;
            if (isset($dt['properties']['type']['enum'])) {
                $dt['properties']['type']['enum'] = array_keys($dataTargets);
            }
        }

        return $baseSchema;
    }

    /**
     * Get schema for execution configuration
     */
    public function getExecutionConfigSchema(): array
    {
        $treeBuilder = $this->configDefinition->getExecutionConfigTreeBuilder();

        return $this->jsonSchemaConverter->convert($treeBuilder);
    }

    /**
     * Get schema information from a service
     * Checks if service implements SchemaAwareInterface and converts TreeBuilder to JSON Schema
     */
    protected function getServiceSchema(string $type, string $class, ServiceLocator $locator): array
    {
        $schema = [
            'type' => $type,
            'class' => $class,
        ];

        try {
            if ($locator->has($type)) {
                $service = $locator->get($type);

                if ($service instanceof SchemaAwareInterface) {
                    $schema['description'] = $service->getSchemaDescription();

                    // Convert TreeBuilder to JSON Schema
                    $treeBuilder = $service->getConfigTreeBuilder();
                    if ($treeBuilder !== null) {
                        $jsonSchema = $this->jsonSchemaConverter->convert($treeBuilder);
                        // Extract properties from the converted schema
                        $schema['settings'] = $jsonSchema['properties'] ?? [];
                    } else {
                        // Service has no settings
                        $schema['settings'] = [];
                    }
                } else {
                    // Fallback for services that don't implement SchemaAwareInterface
                    $schema['description'] = $this->getFallbackDescription($class);
                    $schema['settings'] = [];
                }
            }
        } catch (\Exception $e) {
            // If service cannot be instantiated, use fallback
            $schema['description'] = $this->getFallbackDescription($class);
            $schema['settings'] = [];
        }

        return $schema;
    }

    /**
     * Get fallback description for services that don't implement SchemaAwareInterface
     */
    protected function getFallbackDescription(string $class): string
    {
        // Extract simple description from class name
        $parts = explode('\\', $class);
        $className = end($parts);

        return str_replace(['Strategy', 'Loader', 'Interpreter', 'Operator'], '', $className);
    }
}
