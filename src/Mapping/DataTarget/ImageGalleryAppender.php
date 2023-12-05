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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\ModelInterface;

class ImageGalleryAppender extends Direct
{
    protected bool $ignoreDuplicates;

    /**
     * @param mixed $newData
     * @param object $valueContainer
     * @param string $getter
     *
     * @return bool
     */
    protected function checkAssignData($newData, $valueContainer, $getter): bool
    {
        if (empty($newData) || count($newData->getItems()) == 0) {
            return false;
        }

        /** @var ImageGallery $gallery */
        $gallery = $valueContainer->$getter();

        if ($gallery && $this->ignoreDuplicates) {
            $galleryItems = $gallery->getItems();
            $newImage = $newData->getItems()[0];

            foreach ($galleryItems as $galleryItem) {
                if ($galleryItem->getImage()->getId() == $newImage->getImage()->getId()) {
                    return false;
                }
            }
        }

        //not calling parent implementation as it's only dealing with overwrites which don't apply
        return true;
    }

    /**
     * @param ModelInterface $valueContainer
     * @param string $fieldName
     * @param ImageGallery $data
     *
     * @return void
     */
    protected function doAssignData($valueContainer, $fieldName, $data): void
    {
        $getter = 'get' . ucfirst($fieldName);

        /** @var ImageGallery $gallery */
        $gallery = $valueContainer->$getter();

        if (!$gallery) {
            $gallery = $data;
        } else {
            $galleryItems = $gallery->getItems();
            $newImage = $data->getItems()[0];
            $galleryItems[] = $newImage;
            $gallery->setItems($galleryItems);
        }

        parent::doAssignData($valueContainer, $fieldName, $gallery);
    }

    /**
     * @param array $settings
     *
     * @throws InvalidConfigurationException
     */
    public function setSettings(array $settings): void
    {
        //note - cannot be replaced with ?? as $settings['writeIfSourceIsEmpty'] can be false on purpose
        $this->ignoreDuplicates = isset($settings['ignoreDuplicates']) ? $settings['ignoreDuplicates'] : true;

        parent::setSettings($settings);
    }
}
