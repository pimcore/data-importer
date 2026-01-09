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

use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class AsArray extends AbstractOperator implements SchemaAwareInterface
{
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return array|mixed
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (!is_array($inputData)) {
            $inputData = [$inputData];
        }

        return $inputData;
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     */
    public function evaluateReturnType(string $inputType, ?int $index = null): string
    {
        return TransformationDataTypeService::DEFAULT_ARRAY;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts input data into an array. If the input is not already an array, it wraps the value in an array.';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
