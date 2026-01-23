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
use Pimcore\Bundle\DataImporterBundle\Settings\TransformationTypeAwareInterface;
use Pimcore\Model\DataObject\Data\GeoCoordinates;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class AsGeopoint extends AbstractOperator implements
    SchemaAwareInterface,
    TransformationTypeAwareInterface
{
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return GeoCoordinates
     */
    public function process($inputData, bool $dryRun = false)
    {
        return new GeoCoordinates($inputData[0] ?? null, $inputData[1] ?? null);
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof GeoCoordinates) {
            return 'Lat.: ' . $inputData->getLongitude() . '  Long.: ' . $inputData->getLatitude();
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
                "Unsupported input type '%s' for geoPoint operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return TransformationDataTypeService::GEOPOINT_VALUE;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts an array of coordinates into a GeoCoordinates object (geopoint). '
            . 'Expects an array with 2 values: [latitude, longitude].';
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
            TransformationDataTypeService::GEOPOINT_VALUE
        ];
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
