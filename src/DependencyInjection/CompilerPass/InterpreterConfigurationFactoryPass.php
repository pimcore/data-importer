<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass;

use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\InterpreterFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class InterpreterConfigurationFactoryPass implements CompilerPassInterface
{
    const interpreter_tag = 'pimcore.datahub.batch_import.interpreter';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds(self::interpreter_tag);
        $interpreters = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $interpreters[$attributes['type']] = new Reference($id);
                }
            }
        }

        $serviceLocator = $container->getDefinition(InterpreterFactory::class);
        $serviceLocator->setArgument('$interpreterBluePrints', $interpreters);
    }
}
