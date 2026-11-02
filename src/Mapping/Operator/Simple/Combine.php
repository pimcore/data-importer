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
final class Combine extends AbstractOperator
{
    private string $glue;

    public function setSettings(array $settings): void
    {
        $this->glue = $settings['glue'] ?? ' ';
    }

    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return string
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (!is_array($inputData)) {
            $inputData = [$inputData];
        }

        return implode($this->glue, $inputData);
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
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for combine operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::DEFAULT_TYPE;
    }
}
