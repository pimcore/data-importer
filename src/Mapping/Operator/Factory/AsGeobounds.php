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
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\TransformationTypeAwareInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\DataObject\Data\Geobounds;
use Pimcore\Model\DataObject\Data\GeoCoordinates;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class AsGeobounds extends AbstractOperator implements SchemaAwareInterface,
    TransformationTypeAwareInterface
{
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return Geobounds
     */
    public function process($inputData, bool $dryRun = false)
    {
        $northEast = new GeoCoordinates($inputData[0] ?? null, $inputData[1] ?? null);
        $southWest = new GeoCoordinates($inputData[2] ?? null, $inputData[3] ?? null);

        return new Geobounds($northEast, $southWest);
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof Geobounds) {
            return 'NE: ' . $inputData->getNorthEast() . ' SW: ' . $inputData->getSouthWest();
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
        if ($inputType !== TransformationDataTypeService::DEFAULT_ARRAY) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for geoBounds operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return TransformationDataTypeService::GEOBOUNDS_VALUE;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts an array of coordinates into a Geobounds object. '
            . 'Expects an array with 4 values: [northEastLat, northEastLong, southWestLat, southWestLong].';
    }


    public function getAcceptedInputTypes(): array
    {
        return [
            TransformationDataTypeService::DEFAULT_ARRAY
        ];
    }

    public function getOutputTypes(): array
    {
        return [
            TransformationDataTypeService::GEOBOUNDS_VALUE
        ];
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
