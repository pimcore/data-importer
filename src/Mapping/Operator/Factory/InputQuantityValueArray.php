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
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class InputQuantityValueArray extends QuantityValueArray implements SchemaAwareInterface
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
            $result[$key] = new \Pimcore\Model\DataObject\Data\InputQuantityValue(
                $data[0] ?? null,
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
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        if ($inputType !== TransformationDataTypeService::DEFAULT_ARRAY) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for input quantity value operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return TransformationDataTypeService::INPUT_QUANTITY_VALUE_ARRAY;
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
                if ($data instanceof \Pimcore\Model\DataObject\Data\InputQuantityValue) {
                    $preview[$key] = 'InputQuantityValue: ' . $data->getValue() . ' ' .
                        ($data->getUnit() ? $data->getUnit()->getAbbreviation() : '');
                } else {
                    $preview[$key] = $data;
                }
            }

            return $preview;
        }

        return $inputData;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts an array of value-unit pairs into an array of InputQuantityValue objects. '
            . 'Each item should be [value, unit].';
    }


    public function getAcceptedInputTypes(): array
    {
        return [
            TransformationDataTypeService::DEFAULT_ARRAY
        ];
    }

    public function getOutputTypes(): array
    {
        return [
            TransformationDataTypeService::INPUT_QUANTITY_VALUE_ARRAY
        ];
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
