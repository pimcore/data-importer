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
use Pimcore\Model\Asset;

class AssetLoader implements DataLoaderInterface
{
    /**
     * @var string
     */
    protected $assetPath;

    /**
     * @var string
     */
    protected $temporaryFile = null;

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
        unlink($this->temporaryFile);
    }
}
