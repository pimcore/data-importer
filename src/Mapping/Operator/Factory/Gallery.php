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
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Gallery extends AbstractOperator implements SchemaAwareInterface,
    TransformationTypeAwareInterface
{
    /**
     * @param mixed $inputData
     * @param bool $dryRun
     *
     * @return ImageGallery
     */
    public function process($inputData, bool $dryRun = false)
    {
        $items = [];

        if (!is_array($inputData)) {
            $inputData = [$inputData];
        }

        foreach ($inputData as $asset) {
            if ($asset instanceof Asset\Image) {
                $hotspotImage = new Hotspotimage($asset);
                $items[] = $hotspotImage;
            }
        }

        return new ImageGallery($items);
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
            TransformationDataTypeService::ASSET,
            TransformationDataTypeService::ASSET_ARRAY
        ])) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for gallery operator at transformation position %s",
                $inputType,
                $index
            ));
        }

        return TransformationDataTypeService::GALLERY;
    }

    /**
     * @param mixed $inputData
     *
     * @return array|mixed
     */
    public function generateResultPreview($inputData)
    {
        if ($inputData instanceof ImageGallery) {
            $items = [];

            foreach ($inputData->getItems() as $item) {
                $items[] = 'GalleryImage: ' . ($item->getImage() ? $item->getImage()->getFullPath() : '');
            }

            return $items;
        }

        return $inputData;
    }

    public function getSchemaDescription(): string
    {
        return 'Converts image assets into an ImageGallery object. '
            . 'Takes single or multiple asset images and creates a gallery with hotspot image items.';
    }


    public function getAcceptedInputTypes(): array
    {
        return [
            TransformationDataTypeService::ASSET,
            TransformationDataTypeService::ASSET_ARRAY
        ];
    }

    public function getOutputTypes(): array
    {
        return [
            TransformationDataTypeService::GALLERY
        ];
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
