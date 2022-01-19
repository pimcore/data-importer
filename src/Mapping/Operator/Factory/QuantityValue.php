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
use Pimcore\Model\DataObject\QuantityValue\Unit;

class QuantityValue extends AbstractOperator
{
    /**
     * @var string
     */
    protected $unitSource = 'id';

    /**
     * @var string
     */
    protected $staticUnitId;

    /**
     * @param array $settings
     */
    public function setSettings(array $settings): void
    {
        $this->unitSource = $settings['unitSourceSelect'] ?? 'id';
        $this->staticUnitId = $settings['staticUnitSelect'] ?? null;
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return \Pimcore\Model\DataObject\Data\QuantityValue
     */
    public function process($inputData, bool $dryRun = false)
    {
        $value = null;
        $unitId = null;

        switch ($this->unitSource) {
            case 'id':
                if (is_array($inputData)) {
                    if (isset($inputData[1])) {
                        $unit = Unit::getById($inputData[1]);
                        if ($unit instanceof Unit) {
                            $unitId = $unit->getId();
                        }
                    }
                    $value = $inputData[0];
                }
                break;

            case 'abbr':
                if (is_array($inputData)) {
                    if (isset($inputData[1])) {
                        $unit = Unit::getByAbbreviation($inputData[1]);
                        if ($unit instanceof Unit) {
                            $unitId = $unit->getId();
                        }
                    }
                    $value = $inputData[0];
                }
                break;

            case 'static':
                $value = $inputData;
                if (is_array($inputData)) {
                    $value = $inputData[0];
                }
                $unitId = $this->staticUnitId;
        }

        return new \Pimcore\Model\DataObject\Data\QuantityValue(
            floatval($value ?? null),
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
    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if ($this->unitSource !== 'static') {
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
