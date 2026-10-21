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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $interpreters[$attributes['type']] = new Reference($id);
            }
        }

        // Wire the event dispatcher into every interpreter that supports it (built-in
        // and custom alike), so PreInterpretFileEvent/PreQueueRowEvent are dispatched.
        // This pass runs before child definitions and class parameters are resolved, so
        // both are resolved here. Parents go first: a child inherits their method calls.
        $chains = [];
        foreach (array_keys($taggedServices) as $id) {
            $chains[$id] = $this->getDefinitionChain($container, $id);
        }
        uasort($chains, static fn (array $a, array $b): int => count($a) <=> count($b));

        foreach ($chains as $id => $chain) {
            $class = $this->resolveClass($container, $chain, $id);
            if ($class === null || !method_exists($class, 'setEventDispatcher')) {
                continue;
            }

            foreach ($chain as $definition) {
                if ($definition->hasMethodCall('setEventDispatcher')) {
                    continue 2;
                }
            }

            $chain[0]->addMethodCall('setEventDispatcher', [new Reference('event_dispatcher')]);
        }

        $serviceLocator = $container->getDefinition(InterpreterFactory::class);
        $serviceLocator->setArgument('$interpreterBluePrints', $interpreters);
    }

    /**
     * @return list<Definition> the service definition followed by its parent definitions
     */
    private function getDefinitionChain(ContainerBuilder $container, string $id): array
    {
        $chain = [$container->getDefinition($id)];
        while (end($chain) instanceof ChildDefinition && $container->hasDefinition(end($chain)->getParent())) {
            $chain[] = $container->getDefinition(end($chain)->getParent());
        }

        return $chain;
    }

    /**
     * @param list<Definition> $chain
     */
    private function resolveClass(ContainerBuilder $container, array $chain, string $id): ?string
    {
        $class = $id;
        foreach ($chain as $definition) {
            if ($definition->getClass() !== null) {
                $class = $definition->getClass();

                break;
            }
        }

        $class = $container->getParameterBag()->resolveValue($class);

        return is_string($class) ? $class : null;
    }
}
