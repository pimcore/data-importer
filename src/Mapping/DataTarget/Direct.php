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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service as ElementService;

/**
 * @internal
 */
class Direct implements DataTargetInterface
{
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

        if ($this->fieldName === 'key') {
            if (!$this->checkAssignData($data, $element, 'getKey')) {
                return;
            }
            $this->doAssignData($element, $this->fieldName, $data);
        } elseif ($this->fieldName === 'path') {
            // An element always has a path once it has a parent (which the resolver sets
            // for new elements too), so the target counts as "not empty" only for elements
            // that already exist, i.e. have an ID.
            if (!$this->checkAssignData($data, $element, 'getId')) {
                return;
            }
            $this->doAssignPath($element, $data);
        } elseif (count($setterParts) === 1) {
            //direct class attribute
            $getter = 'get' . ucfirst($this->fieldName);
            if (!$this->checkAssignData($data, $element, $getter)) {
                return;
            }
            $this->doAssignData($element, $this->fieldName, $data);
        } elseif (count($setterParts) === 3) {
            //brick attribute

            $brickContainerGetter = 'get' . ucfirst($setterParts[0]);
            $brickContainer = $element->$brickContainerGetter();

            $brickGetter = 'get' . ucfirst($setterParts[1]);
            $brick = $brickContainer->$brickGetter();

            if (empty($brick)) {
                $brickClassName = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($setterParts[1]);
                $brick = new $brickClassName($element);
                $brick->setFieldname($setterParts[0]);
                $brickSetter = 'set' . ucfirst($setterParts[1]);
                $brickContainer->$brickSetter($brick);
            }

            $getter = 'get' . ucfirst($setterParts[2]);
            if (!$this->checkAssignData($data, $brick, $getter)) {
                return;
            }
            $this->doAssignData($brick, $setterParts[2], $data);
        } else {
            throw new InvalidConfigurationException('Invalid number of setter parts for ' . $this->fieldName);
        }
    }

    /**
     * @param ElementInterface $valueContainer
     * @param string $fieldName
     * @param mixed $data
     *
     * @return void
     */
    protected function doAssignData($valueContainer, $fieldName, $data)
    {
        $setter = 'set' . ucfirst($fieldName);
        $valueContainer->$setter($data, $this->language);
    }

    /**
     * Sets the element's parent based on a path, creating the necessary folder
     * structure if it does not exist yet.
     *
     * @throws InvalidConfigurationException
     * @throws InvalidInputException
     */
    protected function doAssignPath(ElementInterface $element, mixed $data): void
    {
        if (!$element instanceof DataObject) {
            throw new InvalidConfigurationException(
                sprintf('Cannot set path for element of type "%s".', $element::class)
            );
        }

        $path = (string) $data;
        if ($path === '') {
            return;
        }

        // Like the find or create folder location strategy, an existing object at the path can be the parent as well.
        // Folders are only created once the move is known to be allowed, so a rejected row leaves nothing behind.
        $parent = $this->findParentByPath($path);

        // Same restriction as in the resolver: the parent of an existing variant cannot be changed anymore.
        if (
            $element->getId()
            && $element->getType() === AbstractObject::OBJECT_TYPE_VARIANT
            && $parent?->getId() !== $element->getParentId()
        ) {
            throw new InvalidInputException(
                "Element with id `{$element->getId()}` is a variant and cannot change its parent anymore"
            );
        }

        $parent ??= DataObject\Service::createFolderByPath($path);
        if (!$parent instanceof DataObject) {
            throw new InvalidInputException(sprintf('Could not find or create parent for path "%s".', $path));
        }

        $element->setParent($parent);
    }

    /**
     * Looks up an existing parent by the path as given, and by the path with sanitized keys as
     * DataObject\Service::createFolderByPath() would create it, without creating anything.
     */
    private function findParentByPath(string $path): ?DataObject
    {
        $parent = DataObject::getByPath($path);
        if ($parent instanceof DataObject) {
            return $parent;
        }

        $keys = array_filter(explode('/', $path), static fn (string $key) => $key !== '');
        $sanitizedPath = '/' . implode('/', array_map(
            static fn (string $key) => ElementService::getValidKey($key, 'object'),
            $keys
        ));

        return DataObject::getByPath($sanitizedPath);
    }

    /**
     * @param mixed $newData
     * @param object $valueContainer
     * @param string $getter
     *
     * @return bool
     *
     * @throws InvalidConfigurationException
     */
    protected function checkAssignData($newData, $valueContainer, $getter)
    {
        if ($this->writeIfTargetIsNotEmpty === true && $this->writeIfSourceIsEmpty === true) {
            return true;
        }

        $hideUnpublished = DataObject::getHideUnpublished();
        DataObject::setHideUnpublished(false);
        $currentData = $valueContainer->$getter($this->language);
        DataObject::setHideUnpublished($hideUnpublished);

        $fieldName = $this->fieldName;
        //brick attribute
        $fieldNameParts = explode('.', $this->fieldName);
        if (count($fieldNameParts) === 3) {
            $fieldName = $fieldNameParts[2];
        }

        if ($this->fieldName === 'key' || $this->fieldName === 'type' || $this->fieldName === 'path') {
            $currentDataIsEmpty = empty($currentData);
            $newDataIsEmpty = empty($newData);
        } else {
            $fieldDefinition = $this->getFieldDefinition($valueContainer, $fieldName);
            $currentDataIsEmpty = $fieldDefinition->isEmpty($currentData);
            $newDataIsEmpty = $fieldDefinition->isEmpty($newData);
        }

        if ($this->writeIfTargetIsNotEmpty === false && !$currentDataIsEmpty) {
            return false;
        }

        if ($this->writeIfSourceIsEmpty === false && $newDataIsEmpty) {
            return false;
        }

        return true;
    }

    /**
     * @param DataObject\Concrete|DataObject\Objectbrick\Data\AbstractData $valueContainer
     * @param string $fieldName
     *
     * @throws InvalidConfigurationException
     */
    protected function getFieldDefinition(
        Object $valueContainer,
        string $fieldName
    ): Data {
        if ($valueContainer instanceof DataObject\Concrete) {
            $definition = $valueContainer->getClass();
        } elseif ($valueContainer instanceof DataObject\Objectbrick\Data\AbstractData) {
            $definition = $valueContainer->getDefinition();
        } else {
            throw new InvalidConfigurationException('Invalid container type for data attribute.');
        }

        $fieldDefinition = $definition->getFieldDefinition($fieldName);
        if ($fieldDefinition === null) {
            $localizedFields = $definition->getFieldDefinition('localizedfields');
            if ($localizedFields instanceof LocalizedFields) {
                $fieldDefinition = $localizedFields->getFieldDefinition($fieldName);
            }
        }

        if ($fieldDefinition === null) {
            throw new InvalidConfigurationException(sprintf('Field definition for field "%s" not found.', $fieldName));
        }

        return $fieldDefinition;
    }
}
