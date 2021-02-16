<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\File;

class HttpLoader implements DataLoaderInterface
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $schema;

    /**
     * @var string
     */
    protected $importFilePath;


    public function loadData(): string
    {

        $folder = PIMCORE_PRIVATE_VAR . '/datahub_batchimport_tmp';
        File::mkdir($folder);

        $this->importFilePath = $folder . uniqid('http-import-');
        $fullUrl = $this->schema . $this->url;

        if ( copy($fullUrl, $this->importFilePath) ) {
            return $this->importFilePath;
        }else{
            throw new InvalidConfigurationException(fprintf('Could not copy from remote location `%s` to local tmp file `%s`', $fullUrl, $this->importFilePath));
        }

    }

    public function cleanup(): void
    {
        unlink($this->importFilePath);
    }

    public function setSettings(array $settings): void
    {
        if(empty($settings['url'])) {
            throw new InvalidConfigurationException('Empty URL.');
        }
        $this->url = $settings['url'];


        if(empty($settings['schema'])) {
            throw new InvalidConfigurationException('Empty Schema.');
        }
        $this->schema = $settings['schema'];
    }
}
