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
use Pimcore\Bundle\ChangeControlBundle\Subject\SubjectHandlerInterface;
use Pimcore\Bundle\DataImporterBundle\EventListener\DataImporterListener;
use Pimcore\Bundle\DataImporterBundle\Maintenance\RestartQueueWorkersTask;
use Pimcore\Bundle\DataImporterBundle\Messenger\DataImporterHandler;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\ClassExistenceResource;
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

        // Change Control integration is optional: the subject handler and the review
        // hydrator implement its interfaces, so they can only be registered when it is
        // installed. The resource makes that part of what the container is invalidated on.
        $container->addResource(new ClassExistenceResource(SubjectHandlerInterface::class));
        if (interface_exists(SubjectHandlerInterface::class)) {
            $loader->load('services/change_control.yml');

            // the tools need an MCP host to be called through (mcp/sdk) and Studio's tool
            // plumbing to answer through; the propose tool also needs this review lane
            $container->addResource(new ClassExistenceResource(McpTool::class));
            $container->addResource(new ClassExistenceResource(McpToolErrorHandlerInterface::class));
            if (class_exists(McpTool::class) && interface_exists(McpToolErrorHandlerInterface::class)) {
                $loader->load('services/mcp.yml');
            }
        }

        $definition = $container->getDefinition(RestartQueueWorkersTask::class);
        $definition->setArgument('$messengerQueueActivated', $config['messenger_queue_processing']['activated']);
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

        // The Pimcore Agent Bundle reads agent skills from pimcore_agent.skills.paths.
        // Contributing the path here, guarded on the extension being registered, keeps the
        // integration optional: this bundle must not depend on the agent bundle.
        if ($container->hasExtension('pimcore_agent')) {
            $container->prependExtensionConfig('pimcore_agent', [
                'skills' => ['paths' => [__DIR__ . '/../Resources/skills']],
            ]);
        }

        $loader->load('studio_ui.yaml');
        $loader->load('pimcore/studio_backend.yaml');
    }
}
