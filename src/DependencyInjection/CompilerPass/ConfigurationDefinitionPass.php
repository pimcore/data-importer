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

        // Collect all tagged services
        $dataLoaders = [];
        foreach ($container->findTaggedServiceIds(LoaderConfigurationFactoryPass::loader_tag) as $id => $tags) {
            foreach ($tags as $attributes) {
                $dataLoaders[$attributes['type']] = new Reference($id);
            }
        }

        $interpreters = [];
        foreach ($container->findTaggedServiceIds(InterpreterConfigurationFactoryPass::interpreter_tag) as $id => $tags) {
            foreach ($tags as $attributes) {
                $interpreters[$attributes['type']] = new Reference($id);
            }
        }

        $loadStrategies = [];
        foreach ($container->findTaggedServiceIds(ResolverConfigurationFactoryPass::load_tag) as $id => $tags) {
            foreach ($tags as $attributes) {
                $loadStrategies[$attributes['type']] = new Reference($id);
            }
        }

        $locationStrategies = [];
        foreach ($container->findTaggedServiceIds(ResolverConfigurationFactoryPass::location_tag) as $id => $tags) {
            foreach ($tags as $attributes) {
                $locationStrategies[$attributes['type']] = new Reference($id);
            }
        }

        $publishStrategies = [];
        foreach ($container->findTaggedServiceIds(ResolverConfigurationFactoryPass::publish_tag) as $id => $tags) {
            foreach ($tags as $attributes) {
                $publishStrategies[$attributes['type']] = new Reference($id);
            }
        }

        $operators = [];
        foreach ($container->findTaggedServiceIds(MappingConfigurationFactoryPass::operator_tag) as $id => $tags) {
            foreach ($tags as $attributes) {
                $operators[$attributes['type']] = new Reference($id);
            }
        }

        $dataTargets = [];
        foreach ($container->findTaggedServiceIds(MappingConfigurationFactoryPass::data_target_tag) as $id => $tags) {
            foreach ($tags as $attributes) {
                $dataTargets[$attributes['type']] = new Reference($id);
            }
        }

        $cleanupStrategies = [];
        foreach ($container->findTaggedServiceIds(CleanupStrategyConfigurationFactoryPass::cleanup_tag) as $id => $tags) {
            foreach ($tags as $attributes) {
                $cleanupStrategies[$attributes['type']] = new Reference($id);
            }
        }

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
            $schemaDefinition->setArgument('$dataLoaderLocator', $dataLoaderLocator);
            $schemaDefinition->setArgument('$interpreterLocator', $interpreterLocator);
            $schemaDefinition->setArgument('$loadStrategyLocator', $loadStrategyLocator);
            $schemaDefinition->setArgument('$locationStrategyLocator', $locationStrategyLocator);
            $schemaDefinition->setArgument('$publishStrategyLocator', $publishStrategyLocator);
            $schemaDefinition->setArgument('$operatorLocator', $operatorLocator);
            $schemaDefinition->setArgument('$dataTargetLocator', $dataTargetLocator);
            $schemaDefinition->setArgument('$cleanupStrategyLocator', $cleanupStrategyLocator);
        }
    }
}
