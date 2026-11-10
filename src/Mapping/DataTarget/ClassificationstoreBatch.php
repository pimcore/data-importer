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
use Pimcore\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class ClassificationstoreBatch implements DataTargetInterface, ConfigNameAwareInterface
{
    use ClassificationstoreKeyTrait;
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
                $references = $this->parseReferences(array_keys($data));
                $storeId = $this->resolveStoreId($element, $this->fieldName);
                $this->preloadIdentifierReferences($references, $storeId);

                foreach ($data as $key => $value) {
                    $this->assignKeyValue($classificationStore, $references[$key], $element, $value);
                }
            }
        } else {
            throw new InvalidConfigurationException('Field ' . $this->fieldName . ' is not a classification store.');
        }
    }

    /**
     * @param array<int|string, mixed> $tokens
     *
     * @return array<int|string, ClassificationstoreKeyReference> the reference of each token, under the token
     *
     * @throws InvalidInputException
     */
    private function parseReferences(array $tokens): array
    {
        $references = [];
        foreach ($tokens as $token) {
            $reference = ClassificationstoreKeyReference::parse((string) $token);
            if (!$reference instanceof ClassificationstoreKeyReference) {
                throw new InvalidInputException('Key not format <GROUP_IDENTIFIER>:<KEY_IDENTIFIER>: ' . $token);
            }

            if (!$reference->isIdentifier && count(explode('-', $reference->reference)) !== 2) {
                throw new InvalidInputException('Key not format <GROUP_ID>-<KEY_ID>: ' . $token);
            }

            if (!$reference->isIdentifier && !is_numeric($reference->group)) {
                throw new \Exception('groupId not valid');
            }

            if (!$reference->isIdentifier && !is_numeric($reference->key)) {
                throw new \Exception('keyId not valid');
            }

            $references[$token] = $reference;
        }

        return $references;
    }

    /**
     * @throws InvalidInputException
     * @throws InvalidConfigurationException
     */
    private function assignKeyValue(
        \Pimcore\Model\DataObject\Classificationstore $classificationStore,
        ClassificationstoreKeyReference $reference,
        ElementInterface $element,
        mixed $value
    ): void {
        $relation = $this->resolveRelation($reference, $element, $this->fieldName);
        if ($relation instanceof KeyGroupRelation) {
            $groupId = $relation->getGroupId();
            $keyId = $relation->getKeyId();
        } elseif ($reference->isIdentifier) {
            throw new InvalidInputException('Classification store key not found: ' . $reference->token);
        } else {
            $groupId = (int) $reference->group;
            $keyId = (int) $reference->key;
        }

        $declaration = $this->resolveDeclaration($relation);
        if ($reference->variant !== null || ($declaration !== null && $declaration->getVariantNames() !== [])) {
            throw new InvalidInputException(
                'Classification store key ' . $reference->token . ' cannot be written by a Classification Store Batch '
                . 'entry: a batch entry carries no variants. Write that key through a single-key '
                . 'Classification Store entry.'
            );
        }

        $value = $this->buildKeyValue($groupId, $keyId, $value);
        $unbuildableType = $this->unbuildableType($relation, $value);
        if ($unbuildableType !== null) {
            $this->logWarning(
                'Classification store key ' . $reference->token . ' of type ' . $unbuildableType
                . ' was skipped: a Classification Store Batch entry cannot build its value. '
                . 'Write that key through a separate mapping entry ending in the matching operator, e.g. Date.'
            );

            return;
        }

        $this->writeKeyValue(
            $classificationStore,
            $reference->token,
            $groupId,
            $keyId,
            $value,
            $this->language,
            null,
            $declaration
        );
        $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$groupId => true]);
    }

    /**
     * The type of the key when it would store nothing of a value that is one - its field definition normalises the value
     * to null - and null otherwise. Asked of the definition rather than of a list of types, so that a type core adds is
     * answered as it is.
     */
    private function unbuildableType(?KeyGroupRelation $relation, mixed $value): ?string
    {
        if (!$relation instanceof KeyGroupRelation) {
            return null;
        }

        $definition = ClassificationstoreService::getValueDefinition(
            ClassificationstoreService::getFieldDefinitionFromKeyConfig($relation)
        );
        if (!$definition instanceof NormalizerInterface) {
            return null;
        }

        $multiValue = $this->resolveDeclaration($relation)?->isMultiValue() === true;
        $items = $multiValue ? ($this->decodeList($value) ?? [$value]) : [$value];
        foreach ($items as $item) {
            if (!$this->isEmptyValue($item) && $definition->normalize($item) === null) {
                return $definition->getFieldtype();
            }
        }

        return null;
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

        // A pair carrying neither a quantity nor a resolvable unit is no value at all. The same
        // test and the same null are in Operator/Factory/QuantityValue.php:36-37 and in
        // Operator/Factory/QuantityValueArray.php, which this pair shape comes from. Without it
        // the cast below reads a blank source cell as 0.0 and imports missing data as a real zero,
        // where null clears the key — which is what the empty test above does for a plain scalar.
        if ($this->isEmptyValue($quantity) && $unitId === null) {
            return null;
        }

        if ($type === self::TYPE_INPUT_QUANTITY_VALUE) {
            return new InputQuantityValue($quantity, $unitId);
        }

        return new QuantityValue($quantity === null ? null : (float)$quantity, $unitId);
    }

    /**
     * The default unit of the key's own field definition. Not the group's view of it: although a
     * `KeyGroupRelation` is handed in, `Service::getFieldDefinitionFromKeyConfig()` reads
     * `getDefinition()` rather than `getEffectiveDefinition()` and memoises the result under the key
     * id alone, so a per-group `definitionOverride` does not reach here. See follow-up item 5 of
     * `tests/unit/ClassificationStore/CLASSIFICATION_STORE_ROUND_TRIP_AUDIT.md`.
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
