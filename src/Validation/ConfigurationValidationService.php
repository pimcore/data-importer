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

namespace Pimcore\Bundle\DataImporterBundle\Validation;

use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationDefinition;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Service for validating Data Importer configurations
 *
 * This service validates the complete configuration structure by attempting to instantiate
 * all required components through their respective factories. This ensures that:
 * - All required configuration nodes are present
 * - Type values are valid and registered
 * - Settings arrays are valid for each component
 * - Component dependencies are satisfied
 */
class ConfigurationValidationService
{
    protected ConfigurationPreparationService $configurationPreparationService;

    protected DataLoaderFactory $dataLoaderFactory;

    protected InterpreterFactory $interpreterFactory;

    protected ResolverFactory $resolverFactory;

    protected MappingConfigurationFactory $mappingConfigurationFactory;

    protected CleanupStrategyFactory $cleanupStrategyFactory;

    protected ServiceLocator $dataLoaderLocator;

    protected ServiceLocator $interpreterLocator;

    protected ServiceLocator $cleanupStrategyLocator;

    protected Processor $configProcessor;

    protected ConfigurationDefinition $configDefinition;

    public function __construct(
        ConfigurationPreparationService $configurationPreparationService,
        DataLoaderFactory $dataLoaderFactory,
        InterpreterFactory $interpreterFactory,
        ResolverFactory $resolverFactory,
        MappingConfigurationFactory $mappingConfigurationFactory,
        CleanupStrategyFactory $cleanupStrategyFactory,
        ServiceLocator $dataLoaderLocator,
        ServiceLocator $interpreterLocator,
        ServiceLocator $cleanupStrategyLocator,
        ConfigurationDefinition $configDefinition
    ) {
        $this->configurationPreparationService = $configurationPreparationService;
        $this->dataLoaderFactory = $dataLoaderFactory;
        $this->interpreterFactory = $interpreterFactory;
        $this->resolverFactory = $resolverFactory;
        $this->mappingConfigurationFactory = $mappingConfigurationFactory;
        $this->cleanupStrategyFactory = $cleanupStrategyFactory;
        $this->dataLoaderLocator = $dataLoaderLocator;
        $this->interpreterLocator = $interpreterLocator;
        $this->cleanupStrategyLocator = $cleanupStrategyLocator;
        $this->configDefinition = $configDefinition;
        $this->configProcessor = new Processor();
    }

    /**
     * Validate a complete configuration
     *
     * @param array $configuration The configuration array to validate
     *
     * @return ValidationResult Result containing success status and any validation errors
     */
    public function validateConfiguration(array $configuration): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Prepare configuration with defaults
        try {
            $config = $this->configurationPreparationService->prepareConfiguration(
                $configuration['general']['name'] ?? 'validation',
                $configuration,
                true // ignore permissions for validation
            );
        } catch (\Exception $e) {
            $errors[] = new ValidationError('general', 'Configuration preparation failed: ' . $e->getMessage());

            return new ValidationResult(false, $errors, $warnings);
        }

        // Validate loaderConfig
        $loaderErrors = $this->validateLoaderConfig($config['loaderConfig'] ?? []);
        $errors = array_merge($errors, $loaderErrors);

        // Validate interpreterConfig
        $interpreterErrors = $this->validateInterpreterConfig($config['interpreterConfig'] ?? []);
        $errors = array_merge($errors, $interpreterErrors);

        // Validate resolverConfig
        $resolverErrors = $this->validateResolverConfig($config['resolverConfig'] ?? []);
        $errors = array_merge($errors, $resolverErrors);

        // Validate processingConfig
        $processingErrors = $this->validateProcessingConfig($config['processingConfig'] ?? []);
        $errors = array_merge($errors, $processingErrors);

        // Validate mappingConfig
        $mappingErrors = $this->validateMappingConfig(
            $configuration['general']['name'] ?? 'validation',
            $config['mappingConfig'] ?? []
        );
        $errors = array_merge($errors, $mappingErrors);

        // Validate executionConfig
        $executionErrors = $this->validateExecutionConfig($config['executionConfig'] ?? []);
        $errors = array_merge($errors, $executionErrors);

        return new ValidationResult(empty($errors), $errors, $warnings);
    }

    /**
     * Validate loader configuration
     */
    protected function validateLoaderConfig(array $config): array
    {
        $errors = [];

        // Validate using TreeBuilder from ConfigurationDefinition (includes type enum validation)
        try {
            $treeBuilder = $this->configDefinition->getLoaderConfigTreeBuilder();
            $this->configProcessor->process($treeBuilder->buildTree(), [$config]);
        } catch (\Exception $e) {
            $errors[] = new ValidationError('loaderConfig', 'Validation failed: ' . $e->getMessage());

            return $errors;
        }

        // Validate settings using SchemaAwareInterface if available
        $schemaErrors = $this->validateSchemaAwareSettings(
            'loaderConfig',
            $this->dataLoaderLocator,
            $config['type'],
            $config['settings'] ?? []
        );
        $errors = array_merge($errors, $schemaErrors);

        // Also try to instantiate through factory to check dependencies
        try {
            $this->dataLoaderFactory->loadDataLoader($config);
        } catch (InvalidConfigurationException $e) {
            $errors[] = new ValidationError('loaderConfig', $e->getMessage());
        }

        return $errors;
    }

    /**
     * Validate interpreter configuration
     */
    protected function validateInterpreterConfig(array $config): array
    {
        $errors = [];

        // Validate using TreeBuilder from ConfigurationDefinition (includes type enum validation)
        try {
            $treeBuilder = $this->configDefinition->getInterpreterConfigTreeBuilder();
            $this->configProcessor->process($treeBuilder->buildTree(), [$config]);
        } catch (\Exception $e) {
            $errors[] = new ValidationError('interpreterConfig', 'Validation failed: ' . $e->getMessage());

            return $errors;
        }

        // Validate settings using SchemaAwareInterface if available
        $schemaErrors = $this->validateSchemaAwareSettings(
            'interpreterConfig',
            $this->interpreterLocator,
            $config['type'],
            $config['settings'] ?? []
        );
        $errors = array_merge($errors, $schemaErrors);

        // Also try to instantiate through factory to check dependencies
        try {
            $this->interpreterFactory->loadInterpreter(
                'validation',
                $config,
                ['executionType' => ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL],
                null
            );
        } catch (InvalidConfigurationException $e) {
            $errors[] = new ValidationError('interpreterConfig', $e->getMessage());
        }

        return $errors;
    }

    /**
     * Validate resolver configuration
     */
    protected function validateResolverConfig(array $config): array
    {
        $errors = [];

        // Validate using TreeBuilder from ConfigurationDefinition
        try {
            $treeBuilder = $this->configDefinition->getResolverConfigTreeBuilder();
            $this->configProcessor->process($treeBuilder->buildTree(), [$config]);
        } catch (\Exception $e) {
            $errors[] = new ValidationError('resolverConfig', 'Validation failed: ' . $e->getMessage());

            return $errors;
        }

        // Also try to instantiate through factory to check dependencies
        try {
            $this->resolverFactory->loadResolver($config);
        } catch (InvalidConfigurationException $e) {
            $errors[] = new ValidationError('resolverConfig', $e->getMessage());
        }

        return $errors;
    }

    /**
     * Validate processing configuration
     */
    protected function validateProcessingConfig(array $config): array
    {
        $errors = [];

        // Validate using TreeBuilder from ConfigurationDefinition (includes cleanup strategy enum validation)
        try {
            $treeBuilder = $this->configDefinition->getProcessingConfigTreeBuilder();
            $this->configProcessor->process($treeBuilder->buildTree(), [$config]);
        } catch (\Exception $e) {
            $errors[] = new ValidationError('processingConfig', 'Validation failed: ' . $e->getMessage());

            return $errors;
        }

        // Validate cleanup strategy settings using SchemaAwareInterface if available
        if (!empty($config['cleanup']['strategy'])) {
            $strategyType = $config['cleanup']['strategy'];
            $schemaErrors = $this->validateSchemaAwareSettings(
                'processingConfig.cleanup',
                $this->cleanupStrategyLocator,
                $strategyType,
                $config['cleanup']['settings'] ?? []
            );
            $errors = array_merge($errors, $schemaErrors);

            // Also try to instantiate through factory to check dependencies
            try {
                $this->cleanupStrategyFactory->loadCleanupStrategy($strategyType);
            } catch (InvalidConfigurationException $e) {
                $errors[] = new ValidationError('processingConfig.cleanup.strategy', $e->getMessage());
            }
        }

        return $errors;
    }

    /**
     * Validate mapping configuration
     */
    protected function validateMappingConfig(string $configName, array $mappingConfig): array
    {
        $errors = [];

        if (empty($mappingConfig)) {
            $errors[] = new ValidationError('mappingConfig', 'Mapping configuration is required');

            return $errors;
        }

        foreach ($mappingConfig as $index => $mappingItem) {
            try {
                $this->mappingConfigurationFactory->loadMappingConfigurationItem(
                    $configName,
                    $mappingItem,
                    false
                );
            } catch (InvalidConfigurationException $e) {
                $errors[] = new ValidationError("mappingConfig[$index]", $e->getMessage());
            }
        }

        return $errors;
    }

    /**
     * Validate execution configuration
     */
    protected function validateExecutionConfig(array $config): array
    {
        $errors = [];

        // Validate using TreeBuilder from ConfigurationDefinition
        try {
            $treeBuilder = $this->configDefinition->getExecutionConfigTreeBuilder();
            $this->configProcessor->process($treeBuilder->buildTree(), [$config]);
        } catch (\Exception $e) {
            $errors[] = new ValidationError('executionConfig', 'Validation failed: ' . $e->getMessage());
        }

        return $errors;
    }

    /**
     * Helper method to validate settings using SchemaAwareInterface
     *
     * @param string $path Error path prefix
     * @param ServiceLocator $locator Service locator
     * @param string $type Service type
     * @param array $settings Settings to validate
     *
     * @return ValidationError[]
     */
    private function validateSchemaAwareSettings(
        string $path,
        ServiceLocator $locator,
        string $type,
        array $settings
    ): array {
        $errors = [];

        if (!$locator->has($type)) {
            return $errors;
        }

        $service = $locator->get($type);
        if (!$service instanceof SchemaAwareInterface) {
            return $errors;
        }

        $treeBuilder = $service->getConfigTreeBuilder();
        if ($treeBuilder === null || empty($settings)) {
            return $errors;
        }

        try {
            $this->configProcessor->process($treeBuilder->buildTree(), [$settings]);
        } catch (\Exception $e) {
            $errors[] = new ValidationError($path . '.settings', $e->getMessage());
        }

        return $errors;
    }
}
