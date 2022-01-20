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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Loader;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV2\SftpAdapter;
use League\Flysystem\PhpseclibV2\SftpConnectionProvider;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\File;
use Pimcore\Logger;

class SftpLoader implements DataLoaderInterface
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

    public function loadData(): string
    {
        $folder = PIMCORE_PRIVATE_VAR . '/tmp/datahub/dataimporter/sftp-loader/';
        File::mkdir($folder);

        $this->importFilePath = $folder . uniqid('sftp-import-');

        $loggingRemoteUrl = sprintf(
            'ssh2.sftp://%s:%s@%s:%s%s',
            $this->username,
            '***',
            $this->host,
            $this->port,
            $this->remotePath
        );

        $connectionProvider = new SftpConnectionProvider(
            $this->host,
            $this->username,
            $this->password,
            null,
            null,
            $this->port,
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
}
