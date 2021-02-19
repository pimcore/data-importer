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
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;

class InputQuantityValue extends QuantityValue
{
    public function process($inputData, bool $dryRun = false)
    {
        return new \Pimcore\Model\DataObject\Data\QuantityValue(
            $inputData[0] ?? null,
            $inputData[1] ?? null
        );
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
        if ($inputType !== TransformationDataTypeService::DEFAULT_ARRAY) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for input quantity value operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::INPUT_QUANTITY_VALUE;
    }

    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof \Pimcore\Model\DataObject\Data\QuantityValue) {
            return 'InputQuantityValue: ' . $inputData->getValue() . ' ' . ($inputData->getUnit() ? $inputData->getUnit()->getAbbreviation() : '');
        }

        return $inputData;
    }
}
