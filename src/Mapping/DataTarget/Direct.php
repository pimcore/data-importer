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
use Pimcore\Model\DataObject\Data\QuantityValue;
use Pimcore\Model\Element\ElementInterface;

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

        if (count($setterParts) === 1) {
            //direct class attribute
            $setter = 'set' . ucfirst($this->fieldName);
            $getter = 'get' . ucfirst($this->fieldName);
            if (!$this->checkAssignData($data, $element, $getter)) {
                return;
            }
            $element->$setter($data, $this->language);
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
            if (!$this->checkAssignData($data, $brick, $getter)) {
                return;
            }
            $brick->$setter($data, $this->language);
        } else {
            throw new InvalidConfigurationException('Invalid number of setter parts for ' . $this->fieldName);
        }
    }

    /**
     * @param $newData
     * @param $valueContainer
     * @param $getter
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

        if (!empty($currentData) && $this->writeIfTargetIsNotEmpty === false) {
            return false;
        }
        if ($this->writeIfSourceIsEmpty === false && (empty($newData) || ($newData instanceof QuantityValue && empty($newData->getValue())))) {
            return false;
        }

        return true;
    }
}
