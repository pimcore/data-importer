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
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ImageAdvanced extends AbstractOperator implements SchemaAwareInterface
{
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return Hotspotimage|null
     */
    public function process($inputData, bool $dryRun = false)
    {
        if (is_array($inputData)) {
            $inputData = reset($inputData);
        }

        if ($inputData instanceof Asset\Image) {
            return new Hotspotimage($inputData);
        }

        return null;
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
        if (!in_array($inputType, [TransformationDataTypeService::ASSET])) {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for image advanced operator at transformation position %s", $inputType, $index));
        }

        return TransformationDataTypeService::IMAGE_ADVANCED;
    }

    /**
     * @param mixed $inputData
     *
     * @return mixed|string
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof Hotspotimage) {
            return 'Image Advanced: ' . ($inputData->getImage() ? $inputData->getImage()->getFullPath() : '');
        }

        return $inputData;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts an image asset into a Hotspotimage object (advanced image with hotspot/marker support).';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
