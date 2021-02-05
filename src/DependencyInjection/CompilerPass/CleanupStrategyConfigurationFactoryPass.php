<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass;

use Pimcore\Bundle\DataHubBatchImportBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\InterpreterFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class CleanupStrategyConfigurationFactoryPass implements CompilerPassInterface
{
    CONST cleanup_tag = 'pimcore.datahub.batch_import.cleanup';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds(self::cleanup_tag);
        $cleanupStrategies = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $cleanupStrategies[$attributes['type']] = new Reference($id);
                }
            }
        }

        $serviceLocator = $container->getDefinition(CleanupStrategyFactory::class);
        $serviceLocator->setArgument('$cleanupStrategies', $cleanupStrategies);
    }
}
