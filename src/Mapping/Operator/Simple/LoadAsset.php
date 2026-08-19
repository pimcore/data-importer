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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Asset;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class LoadAsset extends ImportAsset implements SchemaAwareInterface
{
    private const LOAD_STRATEGY_ID = 'id';

    private const LOAD_STRATEGY_PATH = 'path';

    private string $loadStrategy;

    public function setSettings(array $settings): void
    {
        $this->loadStrategy = $settings['loadStrategy'] ?? self::LOAD_STRATEGY_PATH;
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     *
     * @throws InvalidConfigurationException
     */
    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        $assets = [];

        foreach ($inputData as $data) {
            $asset = null;
            $cleanData = trim($data);
            if ($this->loadStrategy === self::LOAD_STRATEGY_PATH) {
                $asset = Asset::getByPath($cleanData);
            } elseif ($this->loadStrategy === self::LOAD_STRATEGY_ID) {
                if (is_numeric($cleanData)) {
                    $asset = Asset::getById((int)$cleanData);
                }
            } else {
                throw new InvalidConfigurationException("Unknown load strategy '{ $this->loadStrategy }'");
            }

            if ($asset instanceof Asset) {
                $assets[] = $asset;
            } elseif (!$dryRun && !empty($data)) {
                $this->applicationLogger->warning("Could not load asset from `$data` ", [
                    'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                ]);
            }
        }

        if ($returnScalar) {
            if (!empty($assets)) {
                return reset($assets);
            }

            return null;
        } else {
            return $assets;
        }
    }

    public function getSchemaDescription(): string
    {
        return 'Loads existing Pimcore assets by ID or path. Does not create new assets.';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->enumNode('loadStrategy')
                    ->info('Strategy for loading assets: "path" (by full path) or "id" (by numeric ID)')
                    ->values([self::LOAD_STRATEGY_PATH, self::LOAD_STRATEGY_ID])
                    ->defaultValue(self::LOAD_STRATEGY_PATH)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
