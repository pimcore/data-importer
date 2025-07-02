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
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (!is_array($inputData)) {
            return [];
        }

        $result = [];

        foreach ($inputData as $key => $data) {
            $value = $data[0] ?? null;
            $unitId = $data[1] ?? null;
            if (($value === null || $value === '') && $unitId === null) {
                $result[$key] = null;
                continue;
            } elseif (($value !== null || $value !== '') && $unitId === null) {
                $unitId = $this->getDefaultUnitForClassificationKey($key);
            }
            $result[$key] = new \Pimcore\Model\DataObject\Data\QuantityValue(
                $value === null ? null : floatval($value),
                $unitId
            );
        }

        return $result;
    }

    private function getDefaultUnitForClassificationKey(string $key): ?string
    {
        $keyParts = explode('-', $key);
        if (count($keyParts) !== 2) {
            throw new \Exception('Key not format <GROUP_ID>-<KEY_ID>: ' . $key);
        }

        if (!is_numeric($keyParts[0])) {
            throw new \Exception('groupId not valid');
        }

        if (!is_numeric($keyParts[1])) {
            throw new \Exception('keyId not valid');
        }

        // Try to set the default unit
        $keyConfig = \Pimcore\Model\DataObject\Classificationstore\DefinitionCache::get((int)$keyParts[1]);
        $dataDefinition = \Pimcore\Model\DataObject\Classificationstore\Service::getFieldDefinitionFromKeyConfig(
            $keyConfig
        );

        if ($dataDefinition instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\QuantityValue
            && $dataDefinition->getDefaultUnit() !== null
        ) {
            return $dataDefinition->getDefaultUnit();
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
        if ($inputType !== TransformationDataTypeService::DEFAULT_ARRAY) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for quantity value operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::QUANTITY_VALUE_ARRAY;
    }

    /**
     * @param mixed $inputData
     *
     * @return array|mixed
     */
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
