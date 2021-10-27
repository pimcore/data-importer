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
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

class ManyToManyRelation implements DataTargetInterface
{
    const OVERWRITE_MODE_MERGE = 'merge';
    const OVERWRITE_MODE_REPLACE = 'replace';
    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $language;

    /**
     * @var bool
     */
    protected $writeIfSourceIsEmpty;

    /**
     * @var bool
     */
    protected $writeIfTargetIsNotEmpty;

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
        if (empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->language = $settings['language'] ?? null;

        //note - cannot be replaced with ?? as $settings['writeIfSourceIsEmpty'] can be false on purpose
        $this->writeIfSourceIsEmpty = isset($settings['writeIfSourceIsEmpty']) ? $settings['writeIfSourceIsEmpty'] : true;
        $this->writeIfTargetIsNotEmpty = isset($settings['writeIfTargetIsNotEmpty']) ? $settings['writeIfTargetIsNotEmpty'] : true;

        $this->overwriteMode = $settings['overwriteMode'] ?? self::OVERWRITE_MODE_REPLACE;
    }

    /**
     * @param ElementInterface $element
     * @param mixed $data
     *
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    public function assignData(ElementInterface $element, $data): void
    {
        $setterParts = explode('.', $this->fieldName);
        $hideUnpublished = DataObject::getHideUnpublished();

        if (count($setterParts) === 1) {
            //direct class attribute
            $setter = 'set' . ucfirst($this->fieldName);
            $getter = 'get' . ucfirst($this->fieldName);
            DataObject::setHideUnpublished(false);
            $currentData = $element->$getter($this->language);
            DataObject::setHideUnpublished($hideUnpublished);
            if (!$this->checkAssignData($data, $currentData)) {
                return;
            }
            $element->$setter($this->getPreprocessData($element, $element->getClass(), $this->fieldName, $data), $this->language);
        } elseif (count($setterParts) === 3) {
            //brick attribute

            $brickContainerGetter = 'get' . ucfirst($setterParts[0]);
            $brickContainer = $element->$brickContainerGetter();

            $brickGetter = 'get' . ucfirst($setterParts[1]);
            $brick = $brickContainer->$brickGetter();

            if (empty($brick)) {
                $brickClassName = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($setterParts[1]);
                $brick = new $brickClassName($element);
                $brickSetter = 'set' . ucfirst($setterParts[1]);
                $brickContainer->$brickSetter($brick);
            }

            $setter = 'set' . ucfirst($setterParts[2]);
            $getter = 'get' . ucfirst($setterParts[2]);
            DataObject::setHideUnpublished(false);
            $currentData = $brick->$getter($this->language);
            DataObject::setHideUnpublished($hideUnpublished);
            if (!$this->checkAssignData($data, $currentData)) {
                return;
            }
            $brick->$setter($this->getPreprocessData($brick, $brick->getDefinition(), $setterParts[2], $data), $this->language);
        } else {
            throw new InvalidConfigurationException('Invalid number of setter parts for ' . $this->fieldName);
        }
    }

    /**
     * @param $object
     * @param $definition
     * @param string $attributeName
     * @param $data
     *
     * @return array
     */
    protected function getPreprocessData($object, $definition, string $attributeName, $data)
    {
        $fieldDef = $definition->getFieldDefinition($attributeName);

        switch ($fieldDef->getFieldtype()) {
            case 'manyToManyRelation':
            case 'manyToManyObjectRelation':
            case 'advancedManyToManyRelation':
            case 'advancedManyToManyObjectRelation':
                $getter = 'get' . ucfirst($attributeName);
                $existingData = $object->$getter();

                return $this->getMergedDataArray($existingData ?? [], $data, $fieldDef->getFieldtype());

            default:
                throw new InvalidConfigurationException('Only supports addvanced relation types');
        }
    }

    /**
     * @param array $existingData
     * @param array $data
     * @param string $fieldType
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getMergedDataArray(array $existingData, array $data, string $fieldType): array
    {
        $newData = [];
        switch ($fieldType) {
            case 'manyToManyObjectRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($existingData as $dataObject) {
                        $newData[$dataObject->getId()] = $dataObject;
                    }

                    foreach ($data as $dataObject) {
                        if (!isset($newData[$dataObject->getId()])) {
                            $newData[$dataObject->getId()] = $dataObject;
                        }
                    }
                } else {
                    return $data;
                }
                break;

            case 'advancedManyToManyObjectRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($existingData as $metaDataObject) {
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
                    foreach ($existingData as $element) {
                        $newData[Service::getElementType($element) . '_' . $element->getId()] = $element;
                    }
                    foreach ($data as $element) {
                        if (!isset($newData[Service::getElementType($element) . '_' . $element->getId()])) {
                            $newData[Service::getElementType($element) . '_' . $element->getId()] = $element;
                        }
                    }
                } else {
                    return $data;
                }

                break;

            case 'advancedManyToManyRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($existingData as $metaDataElement) {
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

    /**
     * @param mixed $value Value from element attribute
     *
     * @return bool
     */
    protected function checkAssignData($valueData, $valueAttribute)
    {
        if (!empty($valueAttribute) && $this->writeIfTargetIsNotEmpty === false) {
            return false;
        }
        if (empty($valueData) && $this->writeIfSourceIsEmpty === false) {
            return false;
        }

        return true;
    }
}
