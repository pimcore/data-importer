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

class QuantityValueArray extends AbstractOperator
{
    public function process($inputData, bool $dryRun = false)
    {
        if (!is_array($inputData)) {
            return [];
        }

        $result = [];

        foreach ($inputData as $key => $data) {
            $result[$key] = new \Pimcore\Model\DataObject\Data\QuantityValue(
                floatval($data[0] ?? null),
                $data[1] ?? null
            );
        }

        return $result;
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
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for quantity value operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::QUANTITY_VALUE_ARRAY;
    }

    public function generateResultPreview($inputData)
    {
        if (is_array($inputData)) {
            $preview = [];

            foreach ($inputData as $key => $data) {
                if ($data instanceof \Pimcore\Model\DataObject\Data\QuantityValue) {
                    $preview[$key] = 'QuantityValue: ' . $data->getValue() . ' ' . ($data->getUnit() ? $data->getUnit()->getAbbreviation() : '');
                } else {
                    $preview[$key] = $data;
                }
            }

            return $preview;
        }

        return $inputData;
    }
}
