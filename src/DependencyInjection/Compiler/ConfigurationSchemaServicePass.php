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

namespace Pimcore\Bundle\DataImporterBundle\DependencyInjection\Compiler;

use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ServiceTags;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects all tagged services for the configuration schema service
 */
class ConfigurationSchemaServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ConfigurationSchemaService::class)) {
            return;
        }

        $definition = $container->findDefinition(ConfigurationSchemaService::class);

        // Collect data loaders
        $dataLoaders = $this->collectTaggedServices($container, ServiceTags::LOADER);
        $dataLoaderLocator = ServiceLocatorTagPass::register($container, $dataLoaders);
        $definition->setArgument('$dataLoaderLocator', $dataLoaderLocator);

        // Collect interpreters
        $interpreters = $this->collectTaggedServices($container, ServiceTags::INTERPRETER);
        $interpreterLocator = ServiceLocatorTagPass::register($container, $interpreters);
        $definition->setArgument('$interpreterLocator', $interpreterLocator);

        // Collect load strategies
        $loadStrategies = $this->collectTaggedServices($container, ServiceTags::RESOLVER_LOAD);
        $loadStrategyLocator = ServiceLocatorTagPass::register($container, $loadStrategies);
        $definition->setArgument('$loadStrategyLocator', $loadStrategyLocator);

        // Collect location strategies
        $locationStrategies = $this->collectTaggedServices(
            $container,
            ServiceTags::RESOLVER_LOCATION
        );
        $locationStrategyLocator = ServiceLocatorTagPass::register($container, $locationStrategies);
        $definition->setArgument('$locationStrategyLocator', $locationStrategyLocator);

        // Collect publish strategies
        $publishStrategies = $this->collectTaggedServices($container, ServiceTags::RESOLVER_PUBLISH);
        $publishStrategyLocator = ServiceLocatorTagPass::register($container, $publishStrategies);
        $definition->setArgument('$publishStrategyLocator', $publishStrategyLocator);

        // Collect operators
        $operators = $this->collectTaggedServices($container, ServiceTags::OPERATOR);
        $operatorLocator = ServiceLocatorTagPass::register($container, $operators);
        $definition->setArgument('$operatorLocator', $operatorLocator);

        // Collect data targets
        $dataTargets = $this->collectTaggedServices($container, ServiceTags::DATA_TARGET);
        $dataTargetLocator = ServiceLocatorTagPass::register($container, $dataTargets);
        $definition->setArgument('$dataTargetLocator', $dataTargetLocator);

        // Collect cleanup strategies
        $cleanupStrategies = $this->collectTaggedServices($container, ServiceTags::CLEANUP);
        $cleanupStrategyLocator = ServiceLocatorTagPass::register($container, $cleanupStrategies);
        $definition->setArgument('$cleanupStrategyLocator', $cleanupStrategyLocator);
    }

    /**
     * Collect services with a specific tag and organize them by type attribute
     */
    protected function collectTaggedServices(ContainerBuilder $container, string $tagName): array
    {
        $services = [];
        $taggedServices = $container->findTaggedServiceIds($tagName);

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                if (isset($attributes['type'])) {
                    $services[$attributes['type']] = new Reference($id);
                }
            }
        }

        return $services;
    }
}
