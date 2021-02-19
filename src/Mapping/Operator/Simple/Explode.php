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

class Explode extends AbstractOperator
{
    /**
     * @var string
     */
    protected $delimiter;

    public function setSettings(array $settings): void
    {
        $this->delimiter = $settings['delimiter'] ?? ' ';
    }

    public function process($inputData, bool $dryRun = false)
    {
        if (!empty($this->delimiter)) {
            if (is_array($inputData)) {
                $explodedArray = [];
                foreach ($inputData as $dataRow) {
                    $explodedArray = array_merge($explodedArray, explode($this->delimiter, $dataRow));
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
