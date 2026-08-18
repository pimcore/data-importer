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

use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class InterpreterConfigurationFactoryPass implements CompilerPassInterface
{
    private const INTERPRETER_TAG = 'pimcore.datahub.data_importer.interpreter';

    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds(self::INTERPRETER_TAG);
        $interpreters = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $interpreters[$attributes['type']] = new Reference($id);
                }

                // Wire the event dispatcher into every interpreter that supports it (built-in
                // and custom alike), so PreInterpretFileEvent/PreQueueRowEvent are dispatched.
                $definition = $container->getDefinition($id);
                $class = $definition->getClass() ?? $id;
                if (method_exists($class, 'setEventDispatcher')
                    && !$definition->hasMethodCall('setEventDispatcher')
                ) {
                    $definition->addMethodCall('setEventDispatcher', [new Reference('event_dispatcher')]);
                }
            }
        }

        $serviceLocator = $container->getDefinition(InterpreterFactory::class);
        $serviceLocator->setArgument('$interpreterBluePrints', $interpreters);
    }
}
