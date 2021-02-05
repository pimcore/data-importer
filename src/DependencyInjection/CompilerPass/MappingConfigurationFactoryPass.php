<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DependencyInjection\CompilerPass;

use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\MappingConfigurationFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class MappingConfigurationFactoryPass implements CompilerPassInterface
{
    CONST operator_tag = 'pimcore.datahub.batch_import.operator';
    CONST data_target_tag = 'pimcore.datahub.batch_import.data_target';


    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $taggedServices = $container->findTaggedServiceIds(self::operator_tag);
        $operators = [];
        if (sizeof($taggedServices)) {
            foreach ($taggedServices as $id => $tags) {
                foreach ($tags as $attributes) {
                    $operators[$attributes['type']] = new Reference($id);
                }
            }
        }

        $taggedServices = $container->findTaggedServiceIds(self::data_target_tag);
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
