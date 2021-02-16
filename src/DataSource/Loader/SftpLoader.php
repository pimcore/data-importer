<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader;


use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;
use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\File;

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
        $folder = PIMCORE_PRIVATE_VAR . '/datahub_batchimport_tmp';
        File::mkdir($folder);

        $this->importFilePath = $folder . uniqid('http-import-');

        $loggingRemoteUrl = sprintf(
            'ssh2.sftp://%s:%s@%s:%s%s',
            $this->username,
            '***',
            $this->host,
            $this->port,
            $this->remotePath
        );

        $remoteUrl = sprintf(
            'ssh2.sftp://%s:%s@%s:%s%s',
            $this->username,
            $this->password,
            $this->host,
            $this->port,
            $this->remotePath
        );

        $adapter = new SftpAdapter([
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'root' => '/',
            'timeout' => 10
        ]);

        $filesystem = new Filesystem($adapter);
        $result = $filesystem->copy($this->remotePath, $this->importFilePath);
        if ($result) {
            return $this->importFilePath;
        } else {
            throw new InvalidConfigurationException(sprintf('Could not copy from remote location `%s` to local tmp file `%s`', $loggingRemoteUrl, $this->importFilePath));
        }
    }

    public function cleanup(): void
    {
        unlink($this->importFilePath);
    }

    public function setSettings(array $settings): void
    {
        if(empty($settings['host'])) {
            throw new InvalidConfigurationException('Empty Host.');
        }
        $this->host = $settings['host'];

        if(empty($settings['port'])) {
            throw new InvalidConfigurationException('Empty Port.');
        }
        $this->port = $settings['port'];

        if(empty($settings['username'])) {
            throw new InvalidConfigurationException('Empty Username.');
        }
        $this->username = $settings['username'];

        if(empty($settings['password'])) {
            throw new InvalidConfigurationException('Empty Password.');
        }
        $this->password = $settings['password'];

        if(empty($settings['remotePath'])) {
            throw new InvalidConfigurationException('Empty Remote Path.');
        }
        $this->remotePath = $settings['remotePath'];
    }
}
