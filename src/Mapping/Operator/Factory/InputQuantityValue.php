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
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;

/**
 * @internal
 */
final class InputQuantityValue extends QuantityValue
{
    protected function getDefaultUnitSource(): string
    {
        return 'abbr';
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return \Pimcore\Model\DataObject\Data\InputQuantityValue
     */
    public function process($inputData, bool $dryRun = false)
    {
        [$value, $unitId] = $this->extractValueAndUnitId($inputData);

        return new \Pimcore\Model\DataObject\Data\InputQuantityValue(
            $value,
            $this->applyUnitNullIfNoValue($value, $unitId)
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
        parent::evaluateReturnType($inputType, $index);

        return TransformationDataTypeService::INPUT_QUANTITY_VALUE;
    }

    /**
     * @param mixed $inputData
     *
     * @return string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof \Pimcore\Model\DataObject\Data\InputQuantityValue) {
            return 'InputQuantityValue: ' .
                $inputData->getValue() .
                ($inputData->getUnit() ? ' ['.$inputData->getUnit()->getAbbreviation().']' : '');
        }

        return $inputData;
    }
}
