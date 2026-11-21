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
final class Explode extends AbstractOperator
{
    private string $delimiter;

    private bool $keepSubArrays;

    public function setSettings(array $settings): void
    {
        $this->delimiter = $settings['delimiter'] ?? ' ';
        $this->keepSubArrays = (bool) ($settings['keepSubArrays'] ?? false);
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|array[]|mixed|string[]|\string[][]
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (empty($inputData)) {
            return [];
        }
        if (!empty($this->delimiter)) {
            if (is_array($inputData)) {
                $explodedArray = [];
                foreach ($inputData as $key => $dataRow) {
                    if ($this->keepSubArrays) {
                        $explodedArray[$key] = $this->process($dataRow, $dryRun);
                    } else {
                        $explodedArray = array_merge($explodedArray, [$this->process($dataRow, $dryRun)]);
                    }
                }

                return $explodedArray;
            } else {
                return explode($this->delimiter, $inputData);
            }
        } else {
            return [$inputData];
        }
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
        if (! in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::DEFAULT_ARRAY])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for explode operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::DEFAULT_ARRAY;
    }
}
