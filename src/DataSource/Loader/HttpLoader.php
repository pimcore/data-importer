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

use Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class HttpLoader implements DataLoaderInterface, SchemaAwareInterface
{
    private string $url;

    private string $schema;

    private string $importFilePath;

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    public function loadData(): string
    {
        $folder = PIMCORE_PRIVATE_VAR . '/tmp/datahub/dataimporter/http-loader/';

        $this->importFilePath = $folder . uniqid('http-import-');
        $fullUrl = $this->schema . $this->url;

        try {
            $this->filesystem->copy($fullUrl, $this->importFilePath, true);

            return $this->importFilePath;
        } catch (Exception $ex) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Could not copy from remote location `%s` to local tmp file `%s`',
                    $fullUrl,
                    $this->importFilePath
                ),
                0,
                $ex
            );
        }
    }

    public function cleanup(): void
    {
        unlink($this->importFilePath);
    }

    public function setSettings(array $settings): void
    {
        if (empty($settings['url'])) {
            throw new InvalidConfigurationException('Empty URL.');
        }
        $this->url = $settings['url'];

        if (empty($settings['schema'])) {
            throw new InvalidConfigurationException('Empty Schema.');
        }
        $this->schema = $settings['schema'];
    }

    public function getSchemaDescription(): string
    {
        return 'Load data from HTTP/HTTPS URL';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('URL path to the file (without protocol)')
                ->end()
                ->scalarNode('schema')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Protocol to use (http:// or https://)')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
