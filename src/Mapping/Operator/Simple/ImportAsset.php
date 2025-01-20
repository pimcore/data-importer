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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\DuplicateFullPathException;
use Pimcore\Model\Element\Service;

class ImportAsset extends AbstractOperator
{
    /**
     * @var string
     */
    protected $parentFolderPath;

    /**
     * @var bool
     */
    protected $useExisting;

    /**
     * @var bool
     */
    protected $overwriteExisting;

    /**
     * @var string
     */
    protected $pregMatch;

    public function setSettings(array $settings): void
    {
        $this->parentFolderPath = $settings['parentFolder'] ?? '/';
        $this->useExisting = $settings['useExisting'] ?? false;
        $this->overwriteExisting = $settings['overwriteExisting'] ?? false;
        $this->pregMatch = $settings['pregMatch'] ?? '';
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     *
     * @throws DuplicateFullPathException
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
            $fileUrl = trim($data);

            if (empty($fileUrl)) {
                continue;
            }

            $filename = Service::getValidKey(basename($fileUrl), 'asset');

            if (!empty($this->pregMatch)) {
                $matches = [];
                preg_match($this->pregMatch, $filename, $matches);

                if ($matches !== []) {
                    // Remove the first element since it is the whole matched string
                    // and not the matched sub pattern
                    array_shift($matches);
                    $filename = implode('-', $matches);
                }
            }

            $asset = null;
            if ($this->useExisting) {
                $asset = Asset::getByPath($this->parentFolderPath . '/' . $filename);
            }
            if ($this->overwriteExisting || $asset === null) {
                $options = [
                    'http' => [
                        'method' => 'GET',
                        'header' => 'User-Agent: pimcore-data-importer'
                    ]
                ];
                $context = stream_context_create($options);

                if ($assetData = @file_get_contents($fileUrl, false, $context)) {
                    if ($asset === null) {
                        $parent = Asset\Service::createFolderByPath($this->parentFolderPath);
                        $filename = $this->getSafeFilename($this->parentFolderPath, $filename);

                        $data = [
                            'data' => $assetData,
                            'key' => $filename,
                            'filename' => $filename
                        ];
                        $asset = Asset::create($parent->getId(), $data, false);

                        if ($dryRun) {
                            $asset->correctPath();
                        } else {
                            $asset->save();
                        }
                    } elseif ($this->overwriteExisting && $asset->getData() !== $assetData) {
                        $asset->setData($assetData);

                        if ($dryRun) {
                            $asset->correctPath();
                        } else {
                            $asset->save();
                        }
                    }
                } else {
                    $this->applicationLogger->error("Could not import asset data from `$fileUrl` ", [
                        'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                    ]);
                }
            }

            if ($asset !== null) {
                $assets[] = $asset;
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

    /**
     * @param string $targetPath
     * @param string $filename
     *
     * @return string
     */
    protected function getSafeFilename($targetPath, $filename)
    {
        $pathinfo = pathinfo($filename);
        $originalFilename = $pathinfo['filename'];
        $originalFileextension = empty($pathinfo['extension']) ? '' : '.' . $pathinfo['extension'];
        $count = 1;

        if ($targetPath === '/') {
            $targetPath = '';
        }

        while (true) {
            if (Asset\Service::pathExists($targetPath . '/' . $filename)) {
                $filename = $originalFilename . '_' . $count . $originalFileextension;
                $count++;
            } else {
                return $filename;
            }
        }
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if ($inputType === TransformationDataTypeService::DEFAULT_TYPE) {
            return TransformationDataTypeService::ASSET;
        } elseif ($inputType === TransformationDataTypeService::DEFAULT_ARRAY) {
            return TransformationDataTypeService::ASSET_ARRAY;
        } else {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for import/load asset operator at transformation position %s", $inputType, $index));
        }
    }

    /**
     * @param mixed $inputData
     *
     * @return array|false|mixed
     */
    public function generateResultPreview($inputData)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        foreach ($inputData as &$data) {
            if ($data instanceof Asset) {
                $data = 'Asset: ' . $data->getFullPath();
            }
        }

        if ($returnScalar) {
            return reset($inputData);
        } else {
            return $inputData;
        }
    }
}
