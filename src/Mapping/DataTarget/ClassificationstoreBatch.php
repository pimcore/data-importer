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
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValueUnitSourceTrait;
use Pimcore\Model\DataObject\ClassDefinition\Data\AbstractQuantityValue as AbstractQuantityValueDefinition;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\Service as ClassificationstoreService;
use Pimcore\Model\DataObject\Data\AbstractQuantityValue;
use Pimcore\Model\DataObject\Data\InputQuantityValue;
use Pimcore\Model\DataObject\Data\QuantityValue;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class ClassificationstoreBatch implements DataTargetInterface
{
    use QuantityValueUnitSourceTrait;
    use WriteIfSourceIsEmptyTrait;

    /**
     * Without the opt-in marker this target drops every falsy value before the loop.
     */
    private const WRITES_EMPTY_VALUES_WITHOUT_MARKER = false;

    private const TYPE_QUANTITY_VALUE = 'quantityValue';

    private const TYPE_INPUT_QUANTITY_VALUE = 'inputQuantityValue';

    /**
     * The unit source the operator of each key type reads by default, so that a source column
     * resolves the same way inside a batch entry as it does through that operator:
     * {@see \Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValue} inherits the
     * trait's `id`, {@see \Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\InputQuantityValue}
     * overrides it to `abbr`.
     */
    private const UNIT_SOURCE_BY_TYPE = [
        self::TYPE_QUANTITY_VALUE => 'id',
        self::TYPE_INPUT_QUANTITY_VALUE => 'abbr',
    ];

    private string $fieldName;

    private ?string $language;

    /**
     * @var array<string, KeyGroupRelation|null> the key assignment per `<GROUP_ID>-<KEY_ID>`, held
     *                                           for the run so that one import row does not cost
     *                                           one query per value
     */
    private array $keyGroupRelations = [];

    public function setSettings(array $settings): void
    {
        if (empty($settings['fieldName'])) {
            throw new InvalidConfigurationException('Empty field name.');
        }

        $this->fieldName = $settings['fieldName'];
        $this->language = $settings['language'] ?? null;
        $this->setWriteIfSourceIsEmptySettings($settings, self::WRITES_EMPTY_VALUES_WITHOUT_MARKER);
    }

    /**
     * @param ElementInterface $element
     * @param mixed $data
     *
     * @return void
     *
     * @throws InvalidConfigurationException
     * @throws InvalidInputException
     */
    public function assignData(ElementInterface $element, $data)
    {
        $getter = 'get' . ucfirst($this->fieldName);
        $classificationStore = $element->$getter();

        if ($classificationStore instanceof \Pimcore\Model\DataObject\Classificationstore) {
            if (!is_array($data)) {
                throw new InvalidInputException('Input data not an array');
            }

            if (!$this->writeIfSourceIsEmpty) {
                $data = $this->filterEmptySourceValues($data);
            }

            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $keyParts = explode('-', $key);
                    if (count($keyParts) !== 2) {
                        throw new InvalidInputException('Key not format <GROUP_ID>-<KEY_ID>: ' . $key);
                    }

                    if (!is_numeric($keyParts[0])) {
                        throw new \Exception('groupId not valid');
                    }

                    if (!is_numeric($keyParts[1])) {
                        throw new \Exception('keyId not valid');
                    }

                    $value = $this->buildKeyValue((int)$keyParts[0], (int)$keyParts[1], $value);

                    $classificationStore->setLocalizedKeyValue((int)$keyParts[0], (int)$keyParts[1], $value, $this->language);
                    $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$keyParts[0] => true]);
                }
            }
        } else {
            throw new InvalidConfigurationException('Field ' . $this->fieldName . ' is not a classification store.');
        }
    }

    /**
     * The value for one key of a mixed entry, dispatched on the key's own type: a quantity value key
     * builds its model object here, every other type is written as it arrives.
     *
     * @param int $groupId
     * @param int $keyId
     * @param mixed $value
     *
     * @return mixed
     */
    private function buildKeyValue(int $groupId, int $keyId, $value)
    {
        if ($value instanceof AbstractQuantityValue) {
            return $value;
        }

        // the quantity value operators' own empty test, so that an empty value still clears the key
        if ($this->isEmptyValue($value)) {
            return $value;
        }

        $relation = $this->resolveKeyGroupRelation($groupId, $keyId);
        if (!$relation instanceof KeyGroupRelation) {
            return $value;
        }

        $type = $relation->getType();
        if ($type !== self::TYPE_QUANTITY_VALUE && $type !== self::TYPE_INPUT_QUANTITY_VALUE) {
            return $value;
        }

        if (is_array($value)) {
            // the unit source of this key type's own operator, rather than one default for both
            $this->unitSource = self::UNIT_SOURCE_BY_TYPE[$type];
            [$quantity, $unitId] = $this->extractValueAndUnitId($value);
        } else {
            $quantity = $value;
            $unitId = $this->resolveDefaultUnitId($relation);
        }

        if ($type === self::TYPE_INPUT_QUANTITY_VALUE) {
            return new InputQuantityValue($quantity, $unitId);
        }

        return new QuantityValue($quantity === null ? null : (float)$quantity, $unitId);
    }

    /**
     * @param int $groupId
     * @param int $keyId
     *
     * @return KeyGroupRelation|null
     */
    private function resolveKeyGroupRelation(int $groupId, int $keyId): ?KeyGroupRelation
    {
        $cacheKey = $groupId . '-' . $keyId;
        if (!array_key_exists($cacheKey, $this->keyGroupRelations)) {
            $this->keyGroupRelations[$cacheKey] = KeyGroupRelation::getByGroupAndKeyId($groupId, $keyId);
        }

        return $this->keyGroupRelations[$cacheKey];
    }

    /**
     * The unit of the key's field definition as the group it is assigned to sees it.
     *
     * @param KeyGroupRelation $relation
     *
     * @return string|null
     */
    private function resolveDefaultUnitId(KeyGroupRelation $relation): ?string
    {
        $fieldDefinition = ClassificationstoreService::getFieldDefinitionFromKeyConfig($relation);

        return $fieldDefinition instanceof AbstractQuantityValueDefinition
            ? $fieldDefinition->getDefaultUnit()
            : null;
    }
}
