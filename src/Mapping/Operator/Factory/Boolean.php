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
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Boolean extends AbstractOperator implements SchemaAwareInterface
{
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return bool
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (is_array($inputData)) {
            $inputData = reset($inputData);
        }

        return filter_var($inputData, FILTER_VALIDATE_BOOLEAN);
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
        if (!in_array($inputType, [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::BOOLEAN
        ])) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for boolean operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return TransformationDataTypeService::BOOLEAN;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts input data to boolean value. '
            . 'Uses PHP filter_var with FILTER_VALIDATE_BOOLEAN to evaluate the input.';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
