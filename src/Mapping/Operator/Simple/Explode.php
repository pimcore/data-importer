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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;

class Explode extends AbstractOperator
{
    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var bool
     */
    protected $keepSubArrays;

    public function setSettings(array $settings): void
    {
        $this->delimiter = $settings['delimiter'] ?? ' ';
        $this->keepSubArrays = (bool) ($settings['keepSubArrays'] ?? false);
    }

    public function process($inputData, bool $dryRun = false)
    {
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
    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if (! in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::DEFAULT_ARRAY])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for explode operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::DEFAULT_ARRAY;
    }
}
