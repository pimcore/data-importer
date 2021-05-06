<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass;

use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ResolverConfigurationFactoryPass implements CompilerPassInterface
{
    const load_tag = 'pimcore.datahub.data_importer.resolver.load';
    const location_tag = 'pimcore.datahub.data_importer.resolver.location';
    const publish_tag = 'pimcore.datahub.data_importer.resolver.publish';
    const factory_tag = 'pimcore.datahub.data_importer.resolver.factory';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds(self::load_tag);
        $loadStrategies = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $loadStrategies[$attributes['type']] = new Reference($id);
                }
            }
        }

        $taggedServices = $container->findTaggedServiceIds(self::location_tag);
        $locationStrategies = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $locationStrategies[$attributes['type']] = new Reference($id);
                }
            }
        }

        $taggedServices = $container->findTaggedServiceIds(self::publish_tag);
        $publishStrategies = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $publishStrategies[$attributes['type']] = new Reference($id);
                }
            }
        }

        $taggedServices = $container->findTaggedServiceIds(self::factory_tag);
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
