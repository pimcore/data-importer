<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Asset;

class AssetLoader implements DataLoaderInterface
{

    /**
     * @var string
     */
    protected $assetPath;

    public function loadData(): string
    {
        $asset = Asset::getByPath($this->assetPath);
        if(empty($asset)) {
            throw new InvalidConfigurationException("Asset {$this->assetPath} not found.");
        }

        return $asset->getFileSystemPath();
    }

    public function setSettings(array $settings): void
    {
        if(empty($settings['assetPath'])) {
            throw new InvalidConfigurationException('Empty asset path.');
        }

        $this->assetPath = $settings['assetPath'];
    }

    public function cleanup(): void
    {
        // nothing to do
    }
}
