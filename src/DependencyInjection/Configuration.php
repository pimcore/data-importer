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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 *
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pimcore_data_importer');

        $treeBuilder->getRootNode()->children() // @phpstan-ignore-line
            ->arrayNode('messenger_queue_processing')
                ->addDefaultsIfNotSet()
                ->info('Configure import queue processing via symfony messenger')
                ->children()
                    ->booleanNode('activated')
                        ->defaultFalse()
                        ->info('Activate dispatching messages after import was prepared. Will start import as soon as messages are processed via symfony messenger.')
                    ->end()
                    ->integerNode('worker_count_lifetime')
                        ->defaultValue(60 * 30) //30 minutes
                        ->info('Lifetime of tmp store entry for current worker count entry. After lifetime, the value will be cleared. Default to 30 minutes.')
                    ->end()
                    ->integerNode('worker_count_parallel')
                        ->defaultValue(3)
                        ->info('Count of maximum parallel worker messages for parallel imports.')
                    ->end()
                    ->integerNode('worker_item_count')
                        ->defaultValue(200)
                        ->info('Count of items imported per worker message.')
                    ->end()
                ->end()
            ->end()
            ->arrayNode('mapping_suggestions')
                ->addDefaultsIfNotSet()
                ->info('Configure the suggestions of mapping fields for source columns')
                ->children()
                    ->arrayNode('ai')
                        ->addDefaultsIfNotSet()
                        ->info('Let the Pimcore agent suggest fields for the columns the name comparison leaves open. Needs the agent bundle.')
                        ->children()
                            ->booleanNode('enabled')
                                ->defaultFalse()
                                ->info('Send the headers of open columns and the fields of the class to the agent\'s model.')
                            ->end()
                            ->booleanNode('send_sample_values')
                                ->defaultFalse()
                                ->info('Also send a few values per open column, each column drawn on its own from random rows and cut to 50 characters, so they form no record.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
