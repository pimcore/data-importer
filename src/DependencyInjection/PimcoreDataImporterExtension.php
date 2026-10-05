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

use Pimcore\Bundle\DataHubBundle\DependencyInjection\ConfigProposalLane;
use Pimcore\Bundle\DataImporterBundle\EventListener\DataImporterListener;
use Pimcore\Bundle\DataImporterBundle\Maintenance\RestartQueueWorkersTask;
use Pimcore\Bundle\DataImporterBundle\Messenger\DataImporterHandler;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
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

        $queue = $config['messenger_queue_processing'];
        $definition = $container->getDefinition(DataImporterHandler::class);
        $definition->setArgument('$workerCountLifeTime', $queue['worker_count_lifetime']);
        $definition->setArgument('$workerItemCount', $queue['worker_item_count']);
        $definition->setArgument('$workerCountParallel', $queue['worker_count_parallel']);

        $definition = $container->getDefinition(DataImporterListener::class);
        $definition->setArgument('$messengerQueueActivated', $queue['activated']);

        // proposals ride Change Control and are authored by an agent, both optional peers; the
        // lane itself only ships with a Data Hub recent enough to have it
        if (class_exists(ConfigProposalLane::class) && ConfigProposalLane::canReview($container)) {
            $loader->load('services/change_control.yml');
        }
        if (self::canPropose($container)) {
            $loader->load('services/mcp.yml');
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
            $agentConfig = ['skills' => ['paths' => [__DIR__ . '/../Resources/skills']]];

            // the Configuration agent takes its configuration kinds from the bundles that own them
            if (self::canPropose($container) && self::takesAgentContributions($container)) {
                $agentConfig['agents'] = ['contributions' => ['configuration' => [
                    'pimcoreMcpServers' => ['pimcore-data-importer-read', 'pimcore-data-importer-propose'],
                    'skills' => ['data-importer-configuration'],
                ]]];
            }

            $container->prependExtensionConfig('pimcore_agent', $agentConfig);
        }

        $loader->load('studio_ui.yaml');
        $loader->load('pimcore/studio_backend.yaml');
    }

    private static function canPropose(ContainerBuilder $container): bool
    {
        return class_exists(ConfigProposalLane::class) && ConfigProposalLane::canPropose($container);
    }

    /** an agent bundle released before the contributions node rejects the key */
    private static function takesAgentContributions(ContainerBuilder $container): bool
    {
        $extension = $container->getExtension('pimcore_agent');
        $configuration = $extension instanceof ConfigurationExtensionInterface
            ? $extension->getConfiguration([], $container)
            : null;
        if (!$configuration instanceof ConfigurationInterface) {
            return false;
        }

        $root = $configuration->getConfigTreeBuilder()->buildTree();
        $agents = $root instanceof ArrayNode ? ($root->getChildren()['agents'] ?? null) : null;

        return $agents instanceof ArrayNode && isset($agents->getChildren()['contributions']);
    }
}
