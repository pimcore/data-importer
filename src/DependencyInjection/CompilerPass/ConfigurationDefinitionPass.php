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

namespace Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass;

use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationDefinition;
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaLocators;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to inject ServiceLocators into configuration-related services
 *
 * These services need access to all registered services to:
 * - ConfigurationDefinition: Provide enum values for type fields in TreeBuilder configurations
 * - ConfigurationValidationService: Validate settings using SchemaAwareInterface
 * - ConfigurationSchemaService: Generate complete JSON schemas for AI agents
 */
class ConfigurationDefinitionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ConfigurationDefinition::class)) {
            return;
        }

        // Collect all tagged services using helper method to reduce duplication
        $dataLoaders = $this->collectServicesByType(
            $container,
            LoaderConfigurationFactoryPass::loader_tag
        );
        $interpreters = $this->collectServicesByType(
            $container,
            InterpreterConfigurationFactoryPass::interpreter_tag
        );
        $loadStrategies = $this->collectServicesByType(
            $container,
            ResolverConfigurationFactoryPass::load_tag
        );
        $locationStrategies = $this->collectServicesByType(
            $container,
            ResolverConfigurationFactoryPass::location_tag
        );
        $publishStrategies = $this->collectServicesByType(
            $container,
            ResolverConfigurationFactoryPass::publish_tag
        );
        $operators = $this->collectServicesByType(
            $container,
            MappingConfigurationFactoryPass::operator_tag
        );
        $dataTargets = $this->collectServicesByType(
            $container,
            MappingConfigurationFactoryPass::data_target_tag
        );
        $cleanupStrategies = $this->collectServicesByType(
            $container,
            CleanupStrategyConfigurationFactoryPass::cleanup_tag
        );

        // Create ServiceLocators for each category
        $dataLoaderLocator = ServiceLocatorTagPass::register($container, $dataLoaders);
        $interpreterLocator = ServiceLocatorTagPass::register($container, $interpreters);
        $loadStrategyLocator = ServiceLocatorTagPass::register($container, $loadStrategies);
        $locationStrategyLocator = ServiceLocatorTagPass::register($container, $locationStrategies);
        $publishStrategyLocator = ServiceLocatorTagPass::register($container, $publishStrategies);
        $operatorLocator = ServiceLocatorTagPass::register($container, $operators);
        $dataTargetLocator = ServiceLocatorTagPass::register($container, $dataTargets);
        $cleanupStrategyLocator = ServiceLocatorTagPass::register($container, $cleanupStrategies);

        // Inject into ConfigurationDefinition
        $definition = $container->getDefinition(ConfigurationDefinition::class);
        $definition->setArgument('$dataLoaderLocator', $dataLoaderLocator);
        $definition->setArgument('$interpreterLocator', $interpreterLocator);
        $definition->setArgument('$loadStrategyLocator', $loadStrategyLocator);
        $definition->setArgument('$locationStrategyLocator', $locationStrategyLocator);
        $definition->setArgument('$publishStrategyLocator', $publishStrategyLocator);
        $definition->setArgument('$operatorLocator', $operatorLocator);
        $definition->setArgument('$dataTargetLocator', $dataTargetLocator);
        $definition->setArgument('$cleanupStrategyLocator', $cleanupStrategyLocator);

        // Inject into ConfigurationValidationService (only needs subset)
        if ($container->hasDefinition(ConfigurationValidationService::class)) {
            $validationDefinition = $container->getDefinition(ConfigurationValidationService::class);
            $validationDefinition->setArgument('$dataLoaderLocator', $dataLoaderLocator);
            $validationDefinition->setArgument('$interpreterLocator', $interpreterLocator);
            $validationDefinition->setArgument('$cleanupStrategyLocator', $cleanupStrategyLocator);
        }

        // Inject into ConfigurationSchemaService (needs all ServiceLocators)
        if ($container->hasDefinition(ConfigurationSchemaService::class)) {
            $schemaDefinition = $container->getDefinition(ConfigurationSchemaService::class);
            // Create ConfigurationSchemaLocators bundle
            if (!$container->hasDefinition(ConfigurationSchemaLocators::class)) {
                $locatorsDefinition = $container->register(
                    ConfigurationSchemaLocators::class,
                    ConfigurationSchemaLocators::class
                )->setArguments([
                        $dataLoaderLocator,
                        $interpreterLocator,
                        $loadStrategyLocator,
                        $locationStrategyLocator,
                        $publishStrategyLocator,
                        $operatorLocator,
                        $dataTargetLocator,
                        $cleanupStrategyLocator,
                    ]);
            } else {
                $locatorsDefinition = $container->getDefinition(ConfigurationSchemaLocators::class);
                $locatorsDefinition->setArguments([
                    $dataLoaderLocator,
                    $interpreterLocator,
                    $loadStrategyLocator,
                    $locationStrategyLocator,
                    $publishStrategyLocator,
                    $operatorLocator,
                    $dataTargetLocator,
                    $cleanupStrategyLocator,
                ]);
            }
            // Inject locators bundle into schema service
            $schemaDefinition->setArgument('$locators', $locatorsDefinition);
        }
    }

    /**
     * Helper method to collect services by tag and organize by type attribute
     *
     * @param ContainerBuilder $container
     * @param string $tagName
     *
     * @return array<string, Reference>
     */
    private function collectServicesByType(ContainerBuilder $container, string $tagName): array
    {
        $services = [];
        foreach ($container->findTaggedServiceIds($tagName) as $id => $tags) {
            foreach ($tags as $attributes) {
                $services[$attributes['type']] = new Reference($id);
            }
        }

        return $services;
    }
}
