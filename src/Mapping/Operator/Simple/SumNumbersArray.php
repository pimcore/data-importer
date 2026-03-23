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

class SumNumbersArray extends AbstractOperator
{
    public function setSettings(array $settings): void
    {
        // No settings required
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (!is_array($inputData) && !empty($inputData)) {
            $inputData = [$inputData];
        }

        $sumOfValues = 0;
        array_walk_recursive($inputData, function ($item) use (&$sumOfValues) {
            if (is_numeric($item)) {
                sumOfValues += $item;
            }
        });

        return $sumOfValuesl;
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
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for sum number array operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::DEFAULT_ARRAY;
    }
}
