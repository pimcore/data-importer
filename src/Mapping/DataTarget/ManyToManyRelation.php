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
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Data\ElementMetadata;
use Pimcore\Model\DataObject\Data\ObjectMetadata;
use Pimcore\Model\Element\Service;

class ManyToManyRelation extends Direct
{
    const OVERWRITE_MODE_MERGE = 'merge';
    const OVERWRITE_MODE_REPLACE = 'replace';

    /**
     * @var bool
     */
    protected $overwriteMode;

    /**
     * @param array $settings
     *
     * @throws InvalidConfigurationException
     */
    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);
        $this->overwriteMode = $settings['overwriteMode'] ?? self::OVERWRITE_MODE_REPLACE;
    }

    protected function doAssignData($valueContainer, $fieldName, $data)
    {
        if ($valueContainer instanceof DataObject\Concrete) {
            $definition = $valueContainer->getClass();
        } elseif ($valueContainer instanceof DataObject\Objectbrick\Data\AbstractData) {
            $definition = $valueContainer->getDefinition();
        } else {
            throw new InvalidConfigurationException('Invalid container type for data attribute.');
        }

        $fieldDefinition = $definition->getFieldDefinition($fieldName);

        switch ($fieldDefinition->getFieldtype()) {
            case 'manyToManyRelation':
            case 'manyToManyObjectRelation':
            case 'advancedManyToManyRelation':
            case 'advancedManyToManyObjectRelation':

                $setter = 'set' . ucfirst($fieldName);
                $getter = 'get' . ucfirst($fieldName);
                $valueContainer->$setter(
                    $this->getMergedDataArray($valueContainer, $getter, $fieldDefinition->getFieldtype(), $data),
                    $this->language
                );

                break;

            default:
                throw new InvalidConfigurationException('Invalid field type for attribute ' . $fieldName .
                    '. Only supports advanced relation types, ' . $fieldDefinition->getFieldtype() . ' given.');
        }
    }

    /**
     * @param object $valueContainer
     * @param string $getter
     * @param string $fieldType
     * @param mixed $data
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getMergedDataArray($valueContainer, string $getter, string $fieldType, $data): array
    {
        if (null === $data) {
            return [];
        }

        $currentData = [];
        if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
            $hideUnpublished = DataObject::getHideUnpublished();
            DataObject::setHideUnpublished(false);
            $currentData = $valueContainer->$getter($this->language);
            DataObject::setHideUnpublished($hideUnpublished);
        }

        $newData = [];
        switch ($fieldType) {
            case 'manyToManyObjectRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($currentData as $dataObject) {
                        $newData[$dataObject->getId()] = $dataObject;
                    }

                    foreach ($data as $dataObject) {
                        if (!isset($newData[$dataObject->getId()])) {
                            $newData[$dataObject->getId()] = $dataObject;
                        }
                    }
                } else {
                    return is_array($data) ? $data : [$data];
                }
                break;

            case 'advancedManyToManyObjectRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($currentData as $metaDataObject) {
                        $newData[$metaDataObject->getObject()->getId()] = $metaDataObject;
                    }
                }
                foreach ($data as $dataObject) {
                    if ($this->overwriteMode == self::OVERWRITE_MODE_REPLACE || !isset($newData[$dataObject->getId()])) {
                        $metaDataObject = new ObjectMetadata($this->fieldName, [], $dataObject);
                        $newData[$metaDataObject->getObject()->getId()] = $metaDataObject;
                    }
                }

                break;

            case 'manyToManyRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($currentData as $element) {
                        $newData[Service::getElementType($element) . '_' . $element->getId()] = $element;
                    }
                    foreach ($data as $element) {
                        if (!isset($newData[Service::getElementType($element) . '_' . $element->getId()])) {
                            $newData[Service::getElementType($element) . '_' . $element->getId()] = $element;
                        }
                    }
                } else {
                    return is_array($data) ? $data : [$data];
                }

                break;

            case 'advancedManyToManyRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($currentData as $metaDataElement) {
                        $newData[Service::getElementType($metaDataElement->getElement()) . '_' .
                        $metaDataElement->getElement()->getId()] = $metaDataElement;
                    }
                }
                foreach ($data as $element) {
                    if ($this->overwriteMode == self::OVERWRITE_MODE_REPLACE ||
                        !isset($newData[Service::getElementType($element) . '_' . $element->getId()])) {
                        $metaDataElement = new ElementMetadata($this->fieldName, [], $element);
                        $newData[Service::getElementType($metaDataElement->getElement()) . '_' . $element->getId()] =
                            $metaDataElement;
                    }
                }
                break;

        }

        return array_values($newData);
    }
}
