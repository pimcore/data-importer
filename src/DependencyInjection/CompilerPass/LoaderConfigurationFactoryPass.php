<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass;

use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader\DataLoaderFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class LoaderConfigurationFactoryPass implements CompilerPassInterface
{
    CONST loader_tag = 'pimcore.datahub.batch_import.loader';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds(self::loader_tag);
        $loader = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $loader[$attributes['type']] = new Reference($id);
                }
            }
        }

        $serviceLocator = $container->getDefinition(DataLoaderFactory::class);
        $serviceLocator->setArgument('$dataLoaderBluePrints', $loader);
    }
}
