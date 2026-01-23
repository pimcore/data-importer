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
use Pimcore\Bundle\DataImporterBundle\Settings\TransformationTypeAwareInterface;
use Pimcore\Model\DataObject\Data\RgbaColor;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class AsColor extends AbstractOperator implements
    SchemaAwareInterface,
    TransformationTypeAwareInterface
{
    /**
     * @throws \Exception
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (is_array($inputData)) {
            if (count($inputData) > 0 && is_numeric($inputData[0])) {
                return new RgbaColor(...$inputData);
            }
        } elseif (str_starts_with($inputData, '#')) {
            $color = new RgbaColor();
            $color->setHex($inputData);

            return $color;
        }

        return new RgbaColor();
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof RgbaColor) {
            return $inputData->__toString();
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
        return TransformationDataTypeService::RGBA_COLOR;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts input data into an RGBA color object. '
            . 'Accepts either an array of numeric RGB(A) values or a hex color string starting with #.';
    }

    public function getAcceptedInputTypes(): array
    {
        return [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::DEFAULT_ARRAY
        ];
    }

    public function getOutputTypes(): array
    {
        return [
            TransformationDataTypeService::RGBA_COLOR
        ];
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
