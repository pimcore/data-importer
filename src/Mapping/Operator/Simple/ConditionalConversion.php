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

class ConditionalConversion extends AbstractOperator
{
    /**
     * @var string
     */
    protected $original;

    /**
     * @var string
     */
    protected $converted;

    public function setSettings(array $settings): void
    {
        $this->original = $settings['original'] ?? '';
        $this->converted = $settings['converted'] ?? '';
    }

    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        $origArr = explode('|', $this->original);
        $convArr = explode('|', $this->converted);
        foreach ($inputData as &$data) {
            $index = array_search($data, $origArr);
            if ($index !== false) {
                $data = $convArr[$index];
            } else {
                $index = array_search('*', $origArr);
                if ($index !== false) {
                    $data = $convArr[$index];
                }
            }
        }

        if ($returnScalar) {
            if (!empty($inputData)) {
                return reset($inputData);
            }

            return null;
        } else {
            return $inputData;
        }
    }

    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if (!in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::DEFAULT_ARRAY])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for simple test operator at transformation position %s", $inputType, $index));
        }

        return $inputType;
    }
}
