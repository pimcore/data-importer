<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type\TransformationDataTypeService;

class ReduceArrayKeyValuePairs extends AbstractOperator
{
    public function setSettings(array $settings): void
    {
        //nothing to do
    }

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
    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if ($inputType !== TransformationDataTypeService::DEFAULT_ARRAY) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for reduce array key value pairs operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::DEFAULT_ARRAY;
    }
}
