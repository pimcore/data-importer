<?php
declare(strict_types=1);

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
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScopeAwareTrait;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore as ClassificationstoreDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue as MultiValueDefinition;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig\Listing as GroupConfigListing;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig\Listing as KeyConfigListing;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing as KeyGroupRelationListing;
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;
use Pimcore\Model\DataObject\Classificationstore\Service as ClassificationstoreService;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\MultiValue;
use Pimcore\Model\DataObject\Exception\UnsupportedEngineFeatureException;
use Pimcore\Model\Element\ElementInterface;

/**
 * What the two classification store data targets share: resolving a key reference given as
 * identifiers or as an integer pair, and writing a value that may be qualified by a variant or be one
 * of several.
 *
 * @internal
 */
trait ClassificationstoreKeyTrait
{
    use MappingApplicationScopeAwareTrait;

    private const KEY_MESSAGE_PREFIX = 'Classification store key ';

    private ?string $configName = null;

    /**
     * @var array<string, KeyGroupRelation|null> the key assignment per `<GROUP_ID>-<KEY_ID>`, held
     *                                           for the run so that one import row does not cost
     *                                           one query per value
     */
    private array $keyGroupRelations = [];

    /**
     * @var array<string, string|null> the `<GROUP_ID>-<KEY_ID>` an identifier reference resolved to,
     *                                 per store and reference
     */
    private array $identifierPairs = [];

    /**
     * @var array<string, bool> whether a key is localized in a field, per `<KEY_ID>-<FIELD_NAME>`, held for the run
     *                          so that one value does not cost one resolver call
     */
    private array $keyLocalization = [];

    /**
     * @var array<string, true> the `<KEY_ID>-<FIELD_NAME>` already warned about for lacking a language
     */
    private array $languageWarned = [];

    public function __construct(
        private readonly ApplicationLogger $applicationLogger,
        private readonly LocalizationResolver $localizationResolver
    ) {
    }

    public function setConfigName(string $configName): void
    {
        $this->configName = $configName;
    }

    private function logWarning(string $logMessage): void
    {
        if ($this->reportWarningIfAppliedWithoutSaving($logMessage)) {
            return;
        }

        $this->applicationLogger->warning($logMessage . ' ', [
            'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
        ]);
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function resolveStoreId(ElementInterface $element, string $fieldName): int
    {
        $definition = $element instanceof Concrete ? $element->getClass()->getFieldDefinition($fieldName) : null;
        if (!$definition instanceof ClassificationstoreDefinition) {
            throw new InvalidConfigurationException('Field ' . $fieldName . ' is not a classification store.');
        }

        return (int) $definition->getStoreId();
    }

    /**
     * The language a value of the key is written under: the configured one where the key is localized in the
     * field, none where it is not.
     *
     * @throws InvalidConfigurationException
     */
    private function resolveLanguage(
        ElementInterface $element,
        string $fieldName,
        int $keyId,
        ?string $language
    ): ?string {
        $definition = $element instanceof Concrete ? $element->getClass()->getFieldDefinition($fieldName) : null;
        if (!$definition instanceof ClassificationstoreDefinition) {
            throw new InvalidConfigurationException('Field ' . $fieldName . ' is not a classification store.');
        }

        $cacheKey = $keyId . '-' . $fieldName;
        $this->keyLocalization[$cacheKey] ??= $this->localizationResolver->isKeyLocalized($keyId, $definition);
        if (!$this->keyLocalization[$cacheKey]) {
            return null;
        }

        if ($language === null || $language === '') {
            if (!isset($this->languageWarned[$cacheKey])) {
                $this->languageWarned[$cacheKey] = true;
                $this->logWarning(
                    self::KEY_MESSAGE_PREFIX . $keyId . ' of field ' . $fieldName . ' is localized, but the '
                    . 'mapping entry carries no language; its value is written to the default bucket, not under a '
                    . 'language. Choose a language in the mapping entry.'
                );
            }

            return null;
        }

        return $language;
    }

    /**
     * Reads the assignments of every identifier reference not held yet with three statements, however many there are.
     *
     * @param ClassificationstoreKeyReference[] $references
     */
    private function preloadIdentifierReferences(array $references, int $storeId): void
    {
        $pending = [];
        foreach ($references as $reference) {
            $cacheKey = $this->identifierCacheKey($reference, $storeId);
            if ($reference->isIdentifier && !array_key_exists($cacheKey, $this->identifierPairs)) {
                $pending[] = $reference;
            }
        }

        if ($pending === []) {
            return;
        }

        $groupIds = $this->loadIdsByIdentifier(new GroupConfigListing(), array_column($pending, 'group'), $storeId);
        $keyIds = $this->loadIdsByIdentifier(new KeyConfigListing(), array_column($pending, 'key'), $storeId);

        // read in one go too: the store resolves the definition of every key it is handed a value for
        DefinitionCache::load(array_values($keyIds));

        $relations = [];
        if ($groupIds !== [] && $keyIds !== []) {
            $list = new KeyGroupRelationListing();
            $list->setCondition(
                '`groupId` IN (' . $this->placeholders($groupIds) . ') '
                . 'AND `keyId` IN (' . $this->placeholders($keyIds) . ')',
                [...array_values($groupIds), ...array_values($keyIds)]
            );
            foreach ($list->load() as $relation) {
                $relations[$relation->getGroupId() . '-' . $relation->getKeyId()] = $relation;
            }
        }

        foreach ($pending as $reference) {
            $pair = ($groupIds[$reference->group] ?? 0) . '-' . ($keyIds[$reference->key] ?? 0);
            $cacheKey = $this->identifierCacheKey($reference, $storeId);
            $this->identifierPairs[$cacheKey] = isset($relations[$pair]) ? $pair : null;
            if (isset($relations[$pair])) {
                $this->keyGroupRelations[$pair] = $relations[$pair];
            }
        }
    }

    /**
     * @param GroupConfigListing|KeyConfigListing $list
     * @param string[] $identifiers
     *
     * @return array<string, int> identifier => id
     */
    private function loadIdsByIdentifier($list, array $identifiers, int $storeId): array
    {
        $identifiers = array_values(array_unique($identifiers));
        $list->setCondition(
            '`identifier` IN (' . $this->placeholders($identifiers) . ') AND `storeId` = ?',
            [...$identifiers, $storeId]
        );

        $ids = [];
        foreach ($list->load() as $config) {
            $ids[(string) $config->getIdentifier()] = (int) $config->getId();
        }

        return $ids;
    }

    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private function identifierCacheKey(ClassificationstoreKeyReference $reference, int $storeId): string
    {
        return $storeId . ':' . $reference->reference;
    }

    /**
     * The assignment a reference names, null when the group does not hold the key.
     *
     * @throws InvalidConfigurationException
     */
    private function resolveRelation(
        ClassificationstoreKeyReference $reference,
        ElementInterface $element,
        string $fieldName
    ): ?KeyGroupRelation {
        if (!$reference->isIdentifier) {
            return $this->resolveKeyGroupRelation((int) $reference->group, (int) $reference->key);
        }

        $storeId = $this->resolveStoreId($element, $fieldName);
        $this->preloadIdentifierReferences([$reference], $storeId);
        $pair = $this->identifierPairs[$this->identifierCacheKey($reference, $storeId)];

        return $pair === null ? null : $this->keyGroupRelations[$pair];
    }

    private function resolveKeyGroupRelation(int $groupId, int $keyId): ?KeyGroupRelation
    {
        $cacheKey = $groupId . '-' . $keyId;
        if (!array_key_exists($cacheKey, $this->keyGroupRelations)) {
            $this->keyGroupRelations[$cacheKey] = KeyGroupRelation::getByGroupAndKeyId($groupId, $keyId);
        }

        return $this->keyGroupRelations[$cacheKey];
    }

    /**
     * What the key declares about the qualifiers of its values, null for a key that declares none.
     */
    private function resolveDeclaration(?KeyGroupRelation $relation): ?MultiValueDefinition
    {
        if (!$relation instanceof KeyGroupRelation) {
            return null;
        }

        $definition = ClassificationstoreService::getFieldDefinitionFromKeyConfig($relation);

        return $definition instanceof MultiValueDefinition ? $definition : null;
    }

    /**
     * One value of a key through the setter of its own slot, or - where the key declares several values or the entry
     * names a variant - through the qualified write, which replaces the whole list of the addressed slot.
     *
     * @throws InvalidConfigurationException
     */
    private function writeKeyValue(
        ClassificationstoreModel $classificationStore,
        ClassificationstoreKeySlot $slot,
        mixed $value,
        ?string $language
    ): void {
        $variant = $slot->variant;
        $values = $slot->declaration?->isMultiValue() === true ? $this->decodeList($value) : null;
        if ($variant === null && $values === null) {
            $classificationStore->setLocalizedKeyValue($slot->groupId, $slot->keyId, $value, $language);

            return;
        }

        if ($variant !== null && !in_array($variant, $slot->declaration?->getVariantNames() ?? [], true)) {
            throw new InvalidConfigurationException(
                self::KEY_MESSAGE_PREFIX . $slot->token . ' does not declare the variant `' . $variant . '`.'
            );
        }

        $values ??= [$value];
        $entryLanguage = $language === '' ? null : $language;
        $entries = [];
        foreach (array_values($values) as $position => $item) {
            $entries[] = new MultiValue($item, $entryLanguage, $variant, $position);
        }

        try {
            // an empty list still addresses the slot, so that it clears it
            $classificationStore->setKeyValues(
                $slot->groupId,
                $slot->keyId,
                $entries === [] ? [new MultiValue(null, $entryLanguage, $variant, 0)] : $entries
            );
        } catch (UnsupportedEngineFeatureException) {
            $this->logWarning($this->engineWarning($slot->token, $variant, count($values)));
            $classificationStore->setLocalizedKeyValue(
                $slot->groupId,
                $slot->keyId,
                array_values($values)[0] ?? null,
                $language
            );
        }
    }

    /**
     * The JSON array a source field holds for a key declaring several values; null for anything else.
     *
     * @return array<int, mixed>|null
     */
    private function decodeList(mixed $value): ?array
    {
        if (!is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && array_is_list($decoded) ? $decoded : null;
    }

    private function engineWarning(string $token, ?string $variant, int $valueCount): string
    {
        $qualifiers = [];
        if ($variant !== null) {
            $qualifiers[] = 'the variant `' . $variant . '`';
        }

        if ($valueCount > 1) {
            $qualifiers[] = 'a list of ' . $valueCount . ' values';
        }

        return self::KEY_MESSAGE_PREFIX . $token . ': ' . implode(' and ', $qualifiers) . ' cannot be stored, '
            . 'the engine of the store has to be flipped to one that holds them. '
            . 'The first value was written as position 0 of the default variant.';
    }
}
