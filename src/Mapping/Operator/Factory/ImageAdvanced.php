<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;
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
