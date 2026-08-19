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
use Pimcore\Model\Asset;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class AssetLoader implements DataLoaderInterface, SchemaAwareInterface
{
    private string $assetPath;

    private ?string $temporaryFile = null;

    public function loadData(): string
    {
        $asset = Asset::getByPath($this->assetPath);
        if (empty($asset)) {
            throw new InvalidConfigurationException("Asset {$this->assetPath} not found.");
        }

        $this->temporaryFile = $asset->getTemporaryFile();

        return $this->temporaryFile;
    }

    public function setSettings(array $settings): void
    {
        if (empty($settings['assetPath'])) {
            throw new InvalidConfigurationException('Empty asset path.');
        }

        $this->assetPath = $settings['assetPath'];
    }

    public function cleanup(): void
    {
        if ($this->temporaryFile !== null && is_file($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }

        $this->temporaryFile = null;
    }

    public function getSchemaDescription(): string
    {
        return 'Load data from a Pimcore asset';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('assetPath')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Path to the asset in Pimcore (e.g., /Import/data.csv)')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
