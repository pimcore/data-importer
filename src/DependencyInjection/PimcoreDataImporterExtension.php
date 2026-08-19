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

namespace Pimcore\Bundle\DataImporterBundle\DependencyInjection;

use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Pimcore\Bundle\DataImporterBundle\EventListener\DataImporterListener;
use Pimcore\Bundle\DataImporterBundle\Maintenance\RestartQueueWorkersTask;
use Pimcore\Bundle\DataImporterBundle\Messenger\DataImporterHandler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 *
 * @internal
 */
final class PimcoreDataImporterExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('studio_backend.yaml');

        $definition = $container->getDefinition(DataImporterHandler::class);
        $definition->setArgument('$workerCountLifeTime', $config['messenger_queue_processing']['worker_count_lifetime']);
        $definition->setArgument('$workerItemCount', $config['messenger_queue_processing']['worker_item_count']);
        $definition->setArgument('$workerCountParallel', $config['messenger_queue_processing']['worker_count_parallel']);

        $definition = $container->getDefinition(DataImporterListener::class);
        $definition->setArgument('$messengerQueueActivated', $config['messenger_queue_processing']['activated']);

        $definition = $container->getDefinition(RestartQueueWorkersTask::class);
        $definition->setArgument('$messengerQueueActivated', $config['messenger_queue_processing']['activated']);

        // The MCP tools are only registrable when an MCP host has pulled in mcp/sdk. The
        // resource makes that decision part of what the container is invalidated on, so
        // installing or removing the SDK reshapes the container instead of leaving a stale one.
        $container->addResource(new ClassExistenceResource(McpTool::class));
        if (class_exists(McpTool::class)) {
            $loader->load('services/mcp.yml');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        if ($container->hasExtension('doctrine_migrations')) {
            $loader->load('doctrine_migrations.yml');
        }

        $loader->load('studio_ui.yaml');
        $loader->load('pimcore/studio_backend.yaml');
    }
}
