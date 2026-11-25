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

use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValueUnitSourceTrait;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\ClassificationStoreEngineSupport;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\AbstractQuantityValue as AbstractQuantityValueDefinition;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;
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
    // the shared constructor is aliased rather than inherited, so that the engine question this
    // target asks is not a dependency of the single-key target, which does not ask it
    use ClassificationstoreKeyTrait {
        __construct as private initialiseKeyTrait;
    }
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

    public function __construct(
        ApplicationLogger $applicationLogger,
        LocalizationResolver $localizationResolver,
        private readonly ClassificationStoreEngineSupport $engineSupport
    ) {
        $this->initialiseKeyTrait($applicationLogger, $localizationResolver);
    }

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

            // every token of the entry, before the empty-value filter removes any of them: a
            // malformed token is the entry's error whatever its value is, and a filter that ran
            // first would report it only when it happened to carry a non-empty value
            $references = $this->parseReferences(array_keys($data));

            if (!$this->writeIfSourceIsEmpty) {
                $data = $this->filterEmptySourceValues($data);
                $references = array_intersect_key($references, $data);
            }

            if (!empty($data)) {
                $storeId = $this->resolveStoreId($element, $this->fieldName);
                $this->preloadIdentifierReferences($references, $storeId);

                foreach ($data as $key => $value) {
                    $this->assignKeyValue($classificationStore, $references[$key], $element, $value, $storeId);
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
                throw new InvalidInputException(
                    'Key not format <GROUP_IDENTIFIER>:<KEY_IDENTIFIER> or <GROUP_ID>-<KEY_ID>: ' . $token
                );
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
        mixed $value,
        int $storeId
    ): void {
        $relation = $this->resolveRelation($reference, $element, $this->fieldName);
        if ($relation instanceof KeyGroupRelation) {
            $groupId = $relation->getGroupId();
            $keyId = $relation->getKeyId();
        } elseif ($reference->isIdentifier) {
            throw new InvalidInputException(self::KEY_MESSAGE_PREFIX . 'not found: ' . $reference->token);
        } else {
            $groupId = (int) $reference->group;
            $keyId = (int) $reference->key;
        }

        $unholdableType = $this->unholdableType($relation, $storeId);
        if ($unholdableType !== null) {
            $this->logWarning(
                self::KEY_MESSAGE_PREFIX . $reference->token . ' of type ' . $unholdableType
                . ' was skipped: the `' . $this->engineSupport->engineName($storeId) . '` engine of its store '
                . 'cannot hold a value of that type. The engine of the store has to be flipped to one that holds it.'
            );

            return;
        }

        $language = $this->resolveLanguage($element, $this->fieldName, $keyId, $this->language);

        $value = $this->buildKeyValue($relation, $value, $element, $language);
        $unbuildableType = $this->unbuildableType($relation, $value);
        if ($unbuildableType !== null) {
            $this->logWarning(
                self::KEY_MESSAGE_PREFIX . $reference->token . ' of type ' . $unbuildableType
                . ' was skipped: a Classification Store Batch entry cannot build its value. '
                . 'Write that key through a separate mapping entry ending in the matching operator, e.g. Date.'
            );

            return;
        }

        $this->writeKeyValue(
            $classificationStore,
            new ClassificationstoreKeySlot(
                $reference->token,
                $groupId,
                $keyId,
                $reference->variant,
                $this->resolveDefinition($relation)
            ),
            $value,
            $language
        );
        $classificationStore->setActiveGroups($classificationStore->getActiveGroups() + [$groupId => true]);
    }

    /**
     * The type of a key the engine behind its store cannot hold a value of, and null for one it can.
     * Asked of core per key shape rather than of a list of types,
     * {@see ClassificationStoreEngineSupport::canHoldKeyType()}.
     */
    private function unholdableType(?KeyGroupRelation $relation, int $storeId): ?string
    {
        if (!$relation instanceof KeyGroupRelation) {
            return null;
        }

        $type = $relation->getType();

        return $this->engineSupport->canHoldKeyType($storeId, $type, $this->delegateType($relation))
            ? null
            : $type;
    }

    /**
     * The inner type a wrapper key declares, out of the key's own definition blob - the member the
     * key listing reads with `JSON_EXTRACT` for the same question. Null for a key that declares
     * none, which is every non-wrapper type.
     */
    private function delegateType(KeyGroupRelation $relation): ?string
    {
        $definition = json_decode($relation->getDefinition(), true);
        $delegateType = is_array($definition) ? ($definition['delegateDatatype'] ?? null) : null;

        return is_string($delegateType) ? $delegateType : null;
    }

    /**
     * The type of the key when it would store nothing of a value that is one - its field definition normalises
     * the value to null, or cannot read the value at all - and null otherwise. Asked of the definition rather
     * than of a list of types, so that a type core adds is answered as it is.
     *
     * A definition that raises on the value counts the same way as one that normalises it away: a
     * relation type hands `Element\Service::getElementType()` whatever it was given and raises a
     * `TypeError` on a shape that is no element, which is an `\Error` and therefore passes the
     * `catch (\Exception)` of {@see \Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService}
     * - one unreadable cell then killed the whole import run instead of one key of one row.
     */
    private function unbuildableType(?KeyGroupRelation $relation, mixed $value): ?string
    {
        if (!$relation instanceof KeyGroupRelation) {
            return null;
        }

        $definition = $this->valueDefinition($relation);
        if (!$definition instanceof NormalizerInterface || $this->isEmptyValue($value)) {
            return null;
        }

        try {
            $normalized = $definition->normalize($value);
        } catch (\Throwable) {
            return $definition->getFieldtype();
        }

        return $normalized === null ? $definition->getFieldtype() : null;
    }

    /**
     * The value for one key of a mixed entry, dispatched on the type of the value the key holds: a
     * quantity value key reads the operator's own `[value, unit]` shape here, every other type is
     * built by the type itself out of the shape its `normalize()` emits.
     *
     * The type is read off the key's value definition rather than off the key, because those two are
     * not the same thing for a wrapper: a `multiValue` key's own type is `multiValue` whatever it
     * wraps ({@see \Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue::getFieldType()}), so a
     * dispatch on the key's type skipped a wrapped quantity value key and left its unit unresolved.
     */
    private function buildKeyValue(
        ?KeyGroupRelation $relation,
        mixed $value,
        ElementInterface $element,
        ?string $language
    ): mixed {
        if ($value instanceof AbstractQuantityValue) {
            return $value;
        }

        // the quantity value operators' own empty test, so that an empty value still clears the key
        if ($this->isEmptyValue($value)) {
            return $value;
        }

        if (!$relation instanceof KeyGroupRelation) {
            return $value;
        }

        $definition = $this->valueDefinition($relation);
        $type = $definition?->getFieldtype();
        if ($type !== self::TYPE_QUANTITY_VALUE && $type !== self::TYPE_INPUT_QUANTITY_VALUE) {
            return $this->denormalizeKeyValue($value, $definition, $element, $language);
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
     * The model object the key's own type builds out of the shape its `normalize()` emits. Asked of
     * the definition rather than of a list of types, so that a type core adds is built the way it
     * builds itself - `rgbaColor` reads `{r,g,b,a}`, `geopoint` reads `{latitude,longitude}`, a
     * relation reads `{type,id}`.
     *
     * Only an array is offered: a scalar is already the stored form of every type that takes one.
     * The built value is taken only where it differs from the array and is not null, so that a type
     * whose normalized form IS the array - `multiselect`, `table`, the two code lists - keeps the
     * value it was given, and a type that cannot build one leaves it to
     * {@see self::unbuildableType()} as before.
     *
     * A shape the type cannot read leaves the value as it arrived: the relation operators hand this
     * target the elements themselves rather than the descriptors `normalize()` emits, and
     * denormalizing those raises - `AdvancedManyToManyObjectRelation::denormalize()` subscripts each
     * item. Those values are written as they are, which is what
     * {@see ClassificationstoreKeyTrait::wrapAdvancedRelations()} is there for.
     */
    private function denormalizeKeyValue(
        mixed $value,
        ?Data $definition,
        ElementInterface $element,
        ?string $language
    ): mixed {
        if (!is_array($value) || !$definition instanceof NormalizerInterface) {
            return $value;
        }

        try {
            // geopoint and geobounds bind the value they build to its owner from these
            $denormalized = $definition->denormalize($value, [
                'owner' => $element,
                'fieldname' => $this->fieldName,
                'language' => $language,
            ]);
        } catch (\Throwable) {
            return $value;
        }

        return $denormalized === null || $denormalized === $value ? $value : $denormalized;
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
        $fieldDefinition = $this->valueDefinition($relation);

        return $fieldDefinition instanceof AbstractQuantityValueDefinition
            ? $fieldDefinition->getDefaultUnit()
            : null;
    }

    /**
     * The definition of the VALUE the key of this assignment holds,
     * {@see ClassificationstoreKeyTrait::resolveValueDefinition()}.
     */
    private function valueDefinition(KeyGroupRelation $relation): ?Data
    {
        return $this->resolveValueDefinition($this->resolveDefinition($relation));
    }
}
