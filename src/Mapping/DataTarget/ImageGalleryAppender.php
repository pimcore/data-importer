<?php

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\Element\ElementInterface;
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
        if(empty($newData) || count($newData->getItems()) == 0){
            return false;
        }

        /** @var ImageGallery $gallery */
        $gallery = $valueContainer->$getter();

        if ($gallery && $this->ignoreDuplicates) {
            $galleryItems = $gallery->getItems();
            $newImage = $newData->getItems()[0];

            foreach($galleryItems as $galleryItem) {
                if($galleryItem->getImage()->getId() == $newImage->getImage()->getId()) {
                    return false;
                }
            }
        }

        //not calling parent as it's only dealing with overwrites which don't apply
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
        }
        else{
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
