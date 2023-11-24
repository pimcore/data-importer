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
use Pimcore\Model\DataObject\Data\QuantityValue;
use Pimcore\Model\Element\ElementInterface;

class Classificationstore implements DataTargetInterface
{
    /**
     * @var string
     */
    protected string $fieldName;

    /**
     * @var string
     */
    protected string $language;

    /**
     * @var int
     */
    protected int $keyId;

    /**
     * @var int
     */
    protected int $groupId;

    /**
     * @var bool
     */
    protected bool $writeIfSourceIsEmpty;

    /**
     * @var bool
     */
    protected bool $writeIfTargetIsNotEmpty;

    public function setSettings(array $settings): void
    {
        if (empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $keyParts = explode('-', ($settings['keyId'] ?? []));
        if (empty($keyParts[0]) || empty($keyParts[1])) {
            throw new InvalidConfigurationException('Empty or invalid keyId.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->groupId = (int) $keyParts[0];
        $this->keyId = (int) $keyParts[1];
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
    public function assignData(ElementInterface $element, $data)
    {
        $getter = 'get' . ucfirst($this->fieldName);
        $classificationStore = $element->$getter();

        $currentValue = $classificationStore->getLocalizedKeyValue($this->groupId, $this->keyId);

        if (!$this->shouldAssignData($data, $currentValue)) {
            return;
        }

        if ($classificationStore instanceof \Pimcore\Model\DataObject\Classificationstore) {
            $classificationStore->setLocalizedKeyValue($this->groupId, $this->keyId, $data, $this->language);
            $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$this->groupId => true]);
        } else {
            throw new InvalidConfigurationException('Field ' . $this->fieldName . ' is not a classification store.');
        }
    }

    private function shouldAssignData($newValue, $currentValue): bool
    {
        if ($this->writeIfTargetIsNotEmpty === true && $this->writeIfSourceIsEmpty === true) {
            return true;
        }

        if (!empty($currentValue) && $this->writeIfTargetIsNotEmpty === false) {
            return false;
        }

        if ($this->writeIfSourceIsEmpty === false && (empty($newValue) || ($newValue instanceof QuantityValue && empty($newValue->getValue())))) {
            return false;
        }

        return true;
    }
}
