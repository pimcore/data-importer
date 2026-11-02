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

namespace Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass;

use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class ResolverConfigurationFactoryPass implements CompilerPassInterface
{
    private const LOAD_TAG = 'pimcore.datahub.data_importer.resolver.load';

    private const LOCATION_TAG = 'pimcore.datahub.data_importer.resolver.location';

    private const PUBLISH_TAG = 'pimcore.datahub.data_importer.resolver.publish';

    private const FACTORY_TAG = 'pimcore.datahub.data_importer.resolver.factory';

    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds(self::LOAD_TAG);
        $loadStrategies = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $loadStrategies[$attributes['type']] = new Reference($id);
                }
            }
        }

        $taggedServices = $container->findTaggedServiceIds(self::LOCATION_TAG);
        $locationStrategies = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $locationStrategies[$attributes['type']] = new Reference($id);
                }
            }
        }

        $taggedServices = $container->findTaggedServiceIds(self::PUBLISH_TAG);
        $publishStrategies = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $publishStrategies[$attributes['type']] = new Reference($id);
                }
            }
        }

        $taggedServices = $container->findTaggedServiceIds(self::FACTORY_TAG);
        $factories = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $factories[$attributes['type']] = new Reference($id);
                }
            }
        }

        $serviceLocator = $container->getDefinition(ResolverFactory::class);
        $serviceLocator->setArgument('$loadingStrategyBlueprints', $loadStrategies);
        $serviceLocator->setArgument('$locationStrategyBlueprints', $locationStrategies);
        $serviceLocator->setArgument('$publishingStrategyBlueprints', $publishStrategies);
        $serviceLocator->setArgument('$factoryBlueprints', $factories);
    }
}
