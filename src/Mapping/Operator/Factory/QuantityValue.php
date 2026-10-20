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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;

/**
 * @internal
 */
class QuantityValue extends AbstractOperator
{
    use QuantityValueUnitSourceTrait;

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return \Pimcore\Model\DataObject\Data\AbstractQuantityValue|null
     */
    public function process($inputData, bool $dryRun = false)
    {
        [$value, $unitId] = $this->extractValueAndUnitId($inputData);
        $unitId = $this->applyUnitNullIfNoValue($value, $unitId);
        if ($this->isEmptyValue($value) && $unitId === null) {
            return null;
        }

        return new \Pimcore\Model\DataObject\Data\QuantityValue(
            $value === null ? null : floatval($value),
            $unitId ?? null
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
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if (($this->unitSource ?? $this->getDefaultUnitSource()) !== 'static') {
            if ($inputType !== TransformationDataTypeService::DEFAULT_ARRAY) {
                throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for quantity value operator at transformation position %s",
                    $inputType, $index));
            }
        } elseif ($inputType !== TransformationDataTypeService::DEFAULT_TYPE) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for quantity value operator with static unit at transformation position %s",
                $inputType, $index));
        }

        return TransformationDataTypeService::QUANTITY_VALUE;
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof \Pimcore\Model\DataObject\Data\QuantityValue) {
            return 'QuantityValue: ' . $inputData->getValue() . ' ' .
                ($inputData->getUnit() ? $inputData->getUnit()->getAbbreviation() : '');
        }

        return $inputData;
    }
}
