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

namespace Pimcore\Bundle\DataImporterBundle\DependencyInjection;

use Pimcore\Bundle\DataImporterBundle\EventListener\DataImporterListener;
use Pimcore\Bundle\DataImporterBundle\Maintenance\RestartQueueWorkersTask;
use Pimcore\Bundle\DataImporterBundle\Messenger\DataImporterHandler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class PimcoreDataImporterExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $definition = $container->getDefinition(DataImporterHandler::class);
        $definition->setArgument('$workerCountLifeTime', $config['messenger_queue_processing']['worker_count_lifetime']);
        $definition->setArgument('$workerItemCount', $config['messenger_queue_processing']['worker_item_count']);
        $definition->setArgument('$workerCountParallel', $config['messenger_queue_processing']['worker_count_parallel']);

        $definition = $container->getDefinition(DataImporterListener::class);
        $definition->setArgument('$messengerQueueActivated', $config['messenger_queue_processing']['activated']);

        $definition = $container->getDefinition(RestartQueueWorkersTask::class);
        $definition->setArgument('$messengerQueueActivated', $config['messenger_queue_processing']['activated']);
    }

    public function prepend(ContainerBuilder $container)
    {
        if ($container->hasExtension('doctrine_migrations')) {
            $loader = new YamlFileLoader(
                $container,
                new FileLocator(__DIR__ . '/../Resources/config')
            );

            $loader->load('doctrine_migrations.yml');
        }
    }
}
