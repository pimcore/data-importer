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

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Logger;
use Symfony\Component;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class SftpLoader implements DataLoaderInterface, SchemaAwareInterface
{
    /**
     * @var string
     */
    protected $importFilePath;

    /**
     * @var string
     */
    protected $remotePath;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $port;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    public function __construct(
        protected Component\Filesystem\Filesystem $filesystem
    ) {
    }

    public function loadData(): string
    {
        $folder = PIMCORE_PRIVATE_VAR . '/tmp/datahub/dataimporter/sftp-loader/';
        $this->filesystem->mkdir($folder, 0775);

        $this->importFilePath = $folder . uniqid('sftp-import-');

        $loggingRemoteUrl = sprintf(
            'ssh2.sftp://%s:%s@%s:%s%s',
            $this->username,
            '***',
            $this->host,
            $this->port,
            $this->remotePath
        );

        if (!is_numeric($this->port)) {
            throw new InvalidConfigurationException('The port must be a number');
        }

        $connectionProvider = new SftpConnectionProvider(
            $this->host,
            $this->username,
            $this->password,
            null,
            null,
            (int) $this->port,
            false,
            10
        );

        $filesystem = new Filesystem(new SftpAdapter($connectionProvider, '/'));
        $filesystemLocal = new Filesystem(new LocalFilesystemAdapter('/'));

        try {
            $readStream = $filesystem->readStream($this->remotePath);
            $filesystemLocal->writeStream($this->importFilePath, $readStream);

            return $this->importFilePath;
        } catch (FilesystemException $e) {
            Logger::error($e);

            throw new InvalidConfigurationException(sprintf('Could not copy from remote location `%s` to local tmp file `%s`', $loggingRemoteUrl, $this->importFilePath));
        }
    }

    public function cleanup(): void
    {
        unlink($this->importFilePath);
    }

    public function setSettings(array $settings): void
    {
        if (empty($settings['host'])) {
            throw new InvalidConfigurationException('Empty Host.');
        }
        $this->host = $settings['host'];

        if (empty($settings['port'])) {
            throw new InvalidConfigurationException('Empty Port.');
        }
        $this->port = $settings['port'];

        if (empty($settings['username'])) {
            throw new InvalidConfigurationException('Empty Username.');
        }
        $this->username = $settings['username'];

        if (empty($settings['password'])) {
            throw new InvalidConfigurationException('Empty Password.');
        }
        $this->password = $settings['password'];

        if (empty($settings['remotePath'])) {
            throw new InvalidConfigurationException('Empty Remote Path.');
        }
        $this->remotePath = $settings['remotePath'];
    }

    public function getSchemaDescription(): string
    {
        return 'Load data from SFTP server';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('host')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('SFTP server hostname or IP address')
                ->end()
                ->scalarNode('port')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('22')
                    ->info('SFTP server port')
                ->end()
                ->scalarNode('username')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('SFTP username')
                ->end()
                ->scalarNode('password')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('SFTP password')
                ->end()
                ->scalarNode('remotePath')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Path to the file on the remote server')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
