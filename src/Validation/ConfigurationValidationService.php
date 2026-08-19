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

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationDefinition;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaLocators;
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
    private const MSG_VALIDATION_FAILED = 'Validation failed: ';

    private Processor $configProcessor;

    public function __construct(
        private readonly ConfigurationPreparationService $configurationPreparationService,
        private readonly ConfigurationFactories $factories,
        private readonly ImportProcessingService $importProcessingService,
        private readonly ConfigurationSchemaLocators $locators,
        private readonly ConfigurationDefinition $configDefinition,
    ) {
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
            $config['mappingConfig'] ?? [],
            $config['resolverConfig'] ?? []
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
            $errors[] = new ValidationError('loaderConfig', self::MSG_VALIDATION_FAILED . $e->getMessage());

            return $errors;
        }

        // Validate settings using SchemaAwareInterface if available
        $settings = $this->normalizeSettings($config['settings'] ?? [], 'loaderConfig.settings', $errors);
        if ($settings === null) {
            return $errors;
        }

        $schemaErrors = $this->validateSchemaAwareSettings(
            'loaderConfig',
            $this->locators->dataLoader(),
            $config['type'],
            $settings
        );
        $errors = array_merge($errors, $schemaErrors);

        if (!empty($errors)) {
            return $errors;
        }

        // Also try to instantiate through factory to check dependencies
        try {
            $this->factories->dataLoader()->loadDataLoader($config);
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
            $errors[] = new ValidationError('interpreterConfig', self::MSG_VALIDATION_FAILED . $e->getMessage());

            return $errors;
        }

        // Validate settings using SchemaAwareInterface if available
        $settings = $this->normalizeSettings($config['settings'] ?? [], 'interpreterConfig.settings', $errors);
        if ($settings === null) {
            return $errors;
        }

        $schemaErrors = $this->validateSchemaAwareSettings(
            'interpreterConfig',
            $this->locators->interpreter(),
            $config['type'],
            $settings
        );
        $errors = array_merge($errors, $schemaErrors);

        if (!empty($errors)) {
            return $errors;
        }

        // Also try to instantiate through factory to check dependencies
        try {
            $this->factories->interpreter()->loadInterpreter(
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
            $errors[] = new ValidationError('resolverConfig', self::MSG_VALIDATION_FAILED . $e->getMessage());

            return $errors;
        }

        $errors = array_merge($errors, $this->validateResolverStrategySettings($config));

        // Also try to instantiate through factory to check dependencies
        try {
            $this->factories->resolver()->loadResolver($config);
        } catch (InvalidConfigurationException $e) {
            $errors[] = new ValidationError('resolverConfig', $e->getMessage());
        }

        return $errors;
    }

    /**
     * The four resolver strategies each declare their own settings schema. Without this they
     * were accepted unchecked, so a typo in a strategy setting only surfaced at import time.
     *
     * @param array<string, mixed> $config
     *
     * @return ValidationError[]
     */
    private function validateResolverStrategySettings(array $config): array
    {
        $strategies = [
            'loadingStrategy' => $this->locators->loadStrategy(),
            'createLocationStrategy' => $this->locators->locationStrategy(),
            'locationUpdateStrategy' => $this->locators->locationStrategy(),
            'publishingStrategy' => $this->locators->publishStrategy(),
        ];

        $errors = [];
        foreach ($strategies as $key => $locator) {
            $strategy = $config[$key] ?? null;
            if (!is_array($strategy) || !is_string($strategy['type'] ?? null)) {
                continue;
            }

            $settings = $this->normalizeSettings(
                $strategy['settings'] ?? [],
                'resolverConfig.' . $key . '.settings',
                $errors
            );
            if ($settings === null) {
                continue;
            }

            $errors = array_merge($errors, $this->validateSchemaAwareSettings(
                'resolverConfig.' . $key,
                $locator,
                $strategy['type'],
                $settings
            ));
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
            $errors[] = new ValidationError('processingConfig', self::MSG_VALIDATION_FAILED . $e->getMessage());

            return $errors;
        }

        // Validate cleanup strategy settings using SchemaAwareInterface if available
        if (!empty($config['cleanup']['strategy'])) {
            $strategyType = $config['cleanup']['strategy'];
            $settings = $this->normalizeSettings(
                $config['cleanup']['settings'] ?? [],
                'processingConfig.cleanup.settings',
                $errors
            );
            if ($settings === null) {
                return $errors;
            }

            $schemaErrors = $this->validateSchemaAwareSettings(
                'processingConfig.cleanup',
                $this->locators->cleanupStrategy(),
                $strategyType,
                $settings
            );
            $errors = array_merge($errors, $schemaErrors);

            if ($schemaErrors !== []) {
                return $errors;
            }

            // Also try to instantiate through factory to check dependencies
            try {
                $this->factories->cleanupStrategy()->loadCleanupStrategy($strategyType);
            } catch (InvalidConfigurationException $e) {
                $errors[] = new ValidationError('processingConfig.cleanup.strategy', $e->getMessage());
            }
        }

        return $errors;
    }

    /**
     * Validate mapping configuration
     */
    protected function validateMappingConfig(
        string $configName,
        array $mappingConfig,
        array $resolverConfig
    ): array {

        $errors = [];

        if (empty($mappingConfig)) {
            $errors[] = new ValidationError('mappingConfig', 'Mapping configuration is required');

            return $errors;
        }

        // Validate using TreeBuilder from ConfigurationDefinition (includes cleanup strategy enum validation)
        try {
            $treeBuilder = $this->configDefinition->getMappingConfigTreeBuilder();
            $this->configProcessor->process($treeBuilder->buildTree(), [$mappingConfig]);
        } catch (\Exception $e) {
            $errors[] = new ValidationError('mappingConfig', self::MSG_VALIDATION_FAILED . $e->getMessage());

            return $errors;
        }

        foreach ($mappingConfig as $index => $mappingItem) {
            $errors = array_merge($errors, $this->validateMappingItemSettings((string) $index, $mappingItem));

            try {
                $mappingConfiguration = $this->factories->mappingConfiguration()->loadMappingConfigurationItem(
                    $configName,
                    $mappingItem,
                    false
                );

                // Evaluate transformation result data type
                $transformationResultType = null;

                try {
                    $transformationResultType =
                        $this->importProcessingService
                        ->evaluateTransformationResultDataType(
                            $mappingConfiguration
                        );
                } catch (\Exception $e) {
                    $errors[] = new ValidationError(
                        "mappingConfig[$index].transformationPipeline",
                        'Transformation result type evaluation failed: ' .
                        $e->getMessage()
                    );
                }

                // Validate data target field compatibility
                if ($transformationResultType !== null) {
                    try {
                        $this->validateDataTargetField(
                            $mappingConfiguration,
                            $transformationResultType,
                            $resolverConfig
                        );
                    } catch (\Exception $e) {
                        $errors[] = new ValidationError(
                            "mappingConfig[$index].dataTarget",
                            'Field validation failed: ' . $e->getMessage()
                        );
                    }
                }
            } catch (InvalidConfigurationException $e) {
                $errors[] = new ValidationError("mappingConfig[$index]", $e->getMessage());
            }
        }

        return $errors;
    }

    /**
     * Validate data target field compatibility with transformation
     * result type
     */
    protected function validateDataTargetField(
        object $mappingConfiguration,
        string $transformationResultType,
        array $resolverConfig,
    ): void {
        // Get data target from mapping configuration
        $dataTarget = $mappingConfiguration->getDataTarget();

        // Only validate if data target implements the validator interface
        if (!$dataTarget instanceof
            \Pimcore\Bundle\DataImporterBundle\Settings\DataTargetFieldValidatorInterface
        ) {
            return;
        }

        // Extract class ID from resolver config
        $elementType = $resolverConfig['elementType'] ?? null;

        // Only validate for dataObject imports
        if ($elementType !== 'dataObject') {
            return;
        }

        $classId = $resolverConfig['dataObjectClassId'] ?? null;
        if (is_int($classId)) {
            $classId = (string) $classId;
        }

        if (empty($classId)) {
            throw new InvalidConfigurationException(
                'dataObjectClassId is required in resolverConfig for ' .
                'field validation'
            );
        }

        // Perform field validation
        $dataTarget->validateTargetField(
            $transformationResultType,
            $classId
        );
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
            $errors[] = new ValidationError('executionConfig', self::MSG_VALIDATION_FAILED . $e->getMessage());
        }

        return $errors;
    }

    /**
     * Normalize settings by validating proper format
     *
     * Ensures settings are arrays, not JSON strings. This enforces
     * proper YAML nesting format in configurations.
     *
     * @param mixed $settings Settings value (must be array)
     *
     * Returns null when the value cannot be used, appending the reason to $errors, so the
     * caller keeps collecting rather than throwing out of validateConfiguration().
     * @param ValidationError[] $errors
     *
     * @return array<string, mixed>|null
     */
    private function normalizeSettings(mixed $settings, string $path, array &$errors): ?array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if (is_string($settings)) {
            $errors[] = new ValidationError(
                $path,
                'Settings must be a nested YAML structure, not a JSON ' .
                'string. Use proper YAML nesting: "settings:\\n  ' .
                'fieldName: value" instead of "settings: ' .
                '\\"{\\\"fieldName\\\":\\\"value\\\"}\\""'
            );

            return null;
        }

        if ($settings === null) {
            return [];
        }

        $errors[] = new ValidationError(
            $path,
            'Settings must be an array or null, ' .
            gettype($settings) . ' given'
        );

        return null;
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
    /**
     * The data target and every transformation operator declare their own settings schema.
     *
     * @param array<string, mixed> $mappingItem
     *
     * @return ValidationError[]
     */
    private function validateMappingItemSettings(string $index, array $mappingItem): array
    {
        $errors = [];
        $path = 'mappingConfig[' . $index . ']';

        $dataTarget = $mappingItem['dataTarget'] ?? null;
        if (is_array($dataTarget) && is_string($dataTarget['type'] ?? null)) {
            $settings = $this->normalizeSettings(
                $dataTarget['settings'] ?? [],
                $path . '.dataTarget.settings',
                $errors
            );
            if ($settings !== null) {
                $errors = array_merge($errors, $this->validateSchemaAwareSettings(
                    $path . '.dataTarget',
                    $this->locators->dataTarget(),
                    $dataTarget['type'],
                    $settings
                ));
            }
        }

        foreach (($mappingItem['transformationPipeline'] ?? []) as $step => $operator) {
            if (!is_array($operator) || !is_string($operator['type'] ?? null)) {
                continue;
            }

            $operatorPath = $path . '.transformationPipeline[' . $step . ']';
            $settings = $this->normalizeSettings(
                $operator['settings'] ?? [],
                $operatorPath . '.settings',
                $errors
            );
            if ($settings === null) {
                continue;
            }

            $errors = array_merge($errors, $this->validateSchemaAwareSettings(
                $operatorPath,
                $this->locators->operator(),
                $operator['type'],
                $settings
            ));
        }

        return $errors;
    }

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
        if ($treeBuilder === null) {
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
