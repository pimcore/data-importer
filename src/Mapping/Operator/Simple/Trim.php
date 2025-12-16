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

class Trim extends AbstractOperator
{
    const MODE_BOTH = 'both';

    const MODE_LEFT = 'left';

    const MODE_RIGHT = 'right';

    /**
     * @var string
     */
    protected $mode;

    public function setSettings(array $settings): void
    {
        $this->mode = $settings['mode'] ?? self::MODE_BOTH;
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|false|mixed|null
     */
    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        // Remove null values
        $inputData = array_filter($inputData, static fn($v) => $v !== null);

        foreach ($inputData as &$data) {
            if (!is_string($data)) {
                continue;
            }

            $data = match ($this->mode) {
                self::MODE_BOTH  => trim($data),
                self::MODE_LEFT  => ltrim($data),
                self::MODE_RIGHT => rtrim($data),
                default => $data,
            };
        }
        unset($data);

        if ($returnScalar) {
            return $inputData ? reset($inputData) : null;
        }

        return $inputData;
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
        if (!in_array($inputType, [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::DEFAULT_ARRAY])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for trim operator at transformation position %s", $inputType, $index));
        }

        return $inputType;
    }
}
