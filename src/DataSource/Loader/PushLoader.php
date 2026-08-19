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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Loader;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class PushLoader implements DataLoaderInterface, SchemaAwareInterface
{
    private string $apiKey;

    private bool $ignoreNotEmptyQueue = false;

    private string $importFilePath;

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    public function loadData(): string
    {
        $folder = PIMCORE_PRIVATE_VAR . '/tmp/datahub/dataimporter/push-loader/';
        $this->filesystem->mkdir($folder, 0775);

        $this->importFilePath = $folder . uniqid('push-import-');

        $content = file_get_contents('php://input');
        file_put_contents($this->importFilePath, $content);

        return $this->importFilePath;
    }

    public function cleanup(): void
    {
        unlink($this->importFilePath);
    }

    public function setSettings(array $settings): void
    {
        if (empty($settings['apiKey'])) {
            throw new InvalidConfigurationException('Empty API Key.');
        }
        $this->apiKey = $settings['apiKey'];

        $this->ignoreNotEmptyQueue = $settings['ignoreNotEmptyQueue'] ?? false;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function isIgnoreNotEmptyQueue(): bool
    {
        return $this->ignoreNotEmptyQueue;
    }

    public function getSchemaDescription(): string
    {
        return 'Receive data pushed via API endpoint';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('apiKey')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('API key for authenticating push requests')
                ->end()
                ->booleanNode('ignoreNotEmptyQueue')
                    ->defaultValue(false)
                    ->info('Whether to ignore validation errors when queue is not empty')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
