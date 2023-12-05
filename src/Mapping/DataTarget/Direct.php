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
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Direct\ModelAndTargetField;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\Objectbrick;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\ModelInterface;

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
        $modelWithGetter = $this->getModelWithTarget($element);

        $model = $modelWithGetter->getModel();
        $getter = 'get' . ucfirst($modelWithGetter->getTargetField());

        if (!$this->checkAssignData($data, $model, $getter)) {
            return;
        }

        $this->doAssignData($model, $modelWithGetter->getTargetField(), $data);
    }

    /**
     * @param ModelInterface $valueContainer
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
     * @param mixed $newData
     * @param object $valueContainer
     * @param string $getter
     *
     * @return bool
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

        $fieldName = lcfirst(str_replace('get', '', $getter));
        $fieldDefinition = $this->getFieldDefinition($valueContainer, $fieldName);
        if ($this->writeIfTargetIsNotEmpty === false && !$fieldDefinition->isEmpty($currentData)) {
            return false;
        }

        if ($this->writeIfSourceIsEmpty === false && $fieldDefinition->isEmpty($newData)) {
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

    /**
     * @throws InvalidConfigurationException
     */
    protected function getModelWithTarget(ElementInterface $element): ModelAndTargetField
    {
        $setterParts = explode('.', $this->fieldName);

        if (count($setterParts) === 1) {
            return new ModelAndTargetField($element, $this->fieldName);
        } elseif (count($setterParts) === 3) {
            //brick attribute
            $brickContainerGetter = 'get' . ucfirst($setterParts[0]);
            $brickContainer = $element->$brickContainerGetter();

            $brickGetter = 'get' . ucfirst($setterParts[1]);

            /** @var Objectbrick $brick */
            $brick = $brickContainer->$brickGetter();

            if (empty($brick)) {
                $brickClassName = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($setterParts[1]);
                $brick = new $brickClassName($element);
                $brickSetter = 'set' . ucfirst($setterParts[1]);
                $brickContainer->$brickSetter($brick);
            }

            return new ModelAndTargetField($brick, $setterParts[2]);
        } else {
            throw new InvalidConfigurationException('Invalid number of setter parts for ' . $this->fieldName);
        }
    }
}
