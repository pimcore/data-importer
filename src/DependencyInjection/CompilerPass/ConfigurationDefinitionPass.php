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
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ServiceTags;
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
            ServiceTags::LOADER
        );
        $interpreters = $this->collectServicesByType(
            $container,
            ServiceTags::INTERPRETER
        );
        $loadStrategies = $this->collectServicesByType(
            $container,
            ServiceTags::RESOLVER_LOAD
        );
        $locationStrategies = $this->collectServicesByType(
            $container,
            ServiceTags::RESOLVER_LOCATION
        );
        $publishStrategies = $this->collectServicesByType(
            $container,
            ServiceTags::RESOLVER_PUBLISH
        );
        $operators = $this->collectServicesByType(
            $container,
            ServiceTags::OPERATOR
        );
        $dataTargets = $this->collectServicesByType(
            $container,
            ServiceTags::DATA_TARGET
        );
        $cleanupStrategies = $this->collectServicesByType(
            $container,
            ServiceTags::CLEANUP
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

        // One locator bundle, injected into every consumer that needs the full set.
        $locators = $container->hasDefinition(ConfigurationSchemaLocators::class)
            ? $container->getDefinition(ConfigurationSchemaLocators::class)
            : $container->register(ConfigurationSchemaLocators::class, ConfigurationSchemaLocators::class);
        $locators->setArguments([
            $dataLoaderLocator,
            $interpreterLocator,
            $loadStrategyLocator,
            $locationStrategyLocator,
            $publishStrategyLocator,
            $operatorLocator,
            $dataTargetLocator,
            $cleanupStrategyLocator,
        ]);

        $container->getDefinition(ConfigurationDefinition::class)
            ->setArgument('$locators', $locators);

        if ($container->hasDefinition(ConfigurationValidationService::class)) {
            $container->getDefinition(ConfigurationValidationService::class)
                ->setArgument('$locators', $locators);
        }

        if ($container->hasDefinition(ConfigurationSchemaService::class)) {
            $container->getDefinition(ConfigurationSchemaService::class)
                ->setArgument('$locators', $locators);
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
