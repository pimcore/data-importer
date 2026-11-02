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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;

/**
 * @internal
 */
final class ReduceArrayKeyValuePairs extends AbstractOperator
{
    public function setSettings(array $settings): void
    {
        //nothing to do
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (!is_array($inputData) && !empty($inputData)) {
            $inputData = [$inputData];
        }

        $reducedArray = [];
        while (!empty($inputData)) {
            $reducedArray[array_shift($inputData)] = array_shift($inputData);
        }

        return $reducedArray;
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
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for reduce array key value pairs operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::DEFAULT_ARRAY;
    }
}
