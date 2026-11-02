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

use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class MappingConfigurationFactoryPass implements CompilerPassInterface
{
    private const OPERATOR_TAG = 'pimcore.datahub.data_importer.operator';

    private const DATA_TARGET_TAG = 'pimcore.datahub.data_importer.data_target';

    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds(self::OPERATOR_TAG);
        $operators = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $operators[$attributes['type']] = new Reference($id);
                }
            }
        }

        $taggedServices = $container->findTaggedServiceIds(self::DATA_TARGET_TAG);
        $dataTargets = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $dataTargets[$attributes['type']] = new Reference($id);
                }
            }
        }

        $serviceLocator = $container->getDefinition(MappingConfigurationFactory::class);
        $serviceLocator->setArgument('$operatorBluePrints', $operators);
        $serviceLocator->setArgument('$dataTargetBluePrints', $dataTargets);
    }
}
