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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\Hotspotimage;

class ImageAdvanced extends AbstractOperator
{
    public function process($inputData, bool $dryRun = false)
    {
        if (is_array($inputData)) {
            $inputData = reset($inputData);
        }

        if ($inputData instanceof Asset) {
            return new Hotspotimage($inputData);
        }

        return null;
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if (!in_array($inputType, [TransformationDataTypeService::ASSET])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for image advanced operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::IMAGE_ADVANCED;
    }

    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof Hotspotimage) {
            return 'Image Advanced: ' . ($inputData->getImage() ? $inputData->getImage()->getFullPath() : '');
        }

        return $inputData;
    }
}
