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

use Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Tool\ComposedPathBuilder;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\DuplicateFullPathException;

class ImportAssetComposedPath extends ImportAsset
{
    protected ?string $urlPropertyName;

    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);

        $this->urlPropertyName = $settings["urlPropertyName"] ?? null;
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     *
     * @throws DuplicateFullPathException
     * @throws Exception
     */
    public function process($inputData, bool $dryRun = false)
    {
        if(empty($inputData)){
            return false;
        }
        else if (is_string($inputData)) {
            $inputData = [$inputData];
        }

        $this->parentFolderPath = ComposedPathBuilder::buildPath($inputData, $this->parentFolderPath, 'asset');

        $rawUrl = array_reverse($inputData)[0];
        $assetReturn = parent::process($rawUrl, $dryRun);

        if (!empty($this->urlPropertyName)) {
            $assetReturn->setProperty($this->urlPropertyName, "text", $rawUrl);
            $assetReturn->save();
        }

        return $assetReturn;
    }

    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if ($inputType === TransformationDataTypeService::DEFAULT_TYPE || $inputType === TransformationDataTypeService::DEFAULT_ARRAY) {
            return TransformationDataTypeService::ASSET;
        } else {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for import/load asset operator at transformation position %s", $inputType, $index));
        }
    }


}
