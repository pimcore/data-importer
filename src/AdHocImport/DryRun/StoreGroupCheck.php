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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ValueText;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;

/**
 * What saving a published object would refuse in the classification stores a file writes. Pimcore checks every key of
 * every active group in every language, also those the file does not name, so this looks at them as it does.
 *
 * @internal
 *
 * @phpstan-type GroupKey array{key: StoreKey, label: string, definition: Data}
 * @phpstan-type Refusal array{
 *     field: string,
 *     key: StoreKey|null,
 *     label: string,
 *     value: string,
 *     error: ValueError,
 *     languages: list<string>
 * }
 */
final class StoreGroupCheck
{
    private const string DEFAULT_LANGUAGE = 'default';

    /** @var array<int, list<GroupKey>> a group's keys, read once per run */
    private array $keys = [];

    /**
     * @param list<string> $fields the classification store fields the file writes
     * @param list<string> $languages the valid languages of the system
     * @param \Closure(int): list<GroupKey> $keysOfGroup
     */
    public function __construct(
        private readonly array $fields,
        private readonly array $languages,
        private readonly \Closure $keysOfGroup,
    ) {
    }

    /**
     * The object's stores as they are now, to call before a row is written.
     *
     * @return array<string, Classificationstore>
     */
    public function stores(Concrete $object): array
    {
        $stores = [];
        foreach ($this->fields as $field) {
            $store = $object->get($field);
            if ($store instanceof Classificationstore) {
                $stores[$field] = clone $store;
            }
        }

        return $stores;
    }

    /**
     * What a save would refuse once the written row's unusable values are left out. Those are undone in the object
     * (and with them a group only they activated), then the values of the keys refused here, as far as the file
     * writes them, until no more can be left out.
     *
     * @param array<string, Classificationstore> $before the row's stores from stores()
     * @param array<int, FieldWriter> $writers
     * @param array<int, true> $leftOut the writers whose value their field cannot take
     * @param array<int|string, mixed> $row
     *
     * @return array{lifted: list<Refusal>, remaining: list<Refusal>} lifted by leaving the file's values out, and
     *     refused whatever the row leaves out
     */
    public function refusedWithout(
        Concrete $object,
        array $before,
        array $writers,
        array $leftOut,
        int $index,
        array $row
    ): array {
        $lifted = [];
        do {
            $this->leaveOut($object, $before, $writers, $leftOut, $index, $row);
            $refused = $this->refused($object);
            $more = [];
            foreach ($refused as $refusal) {
                $positions = array_diff_key($this->writersOf($writers, $refusal), $leftOut);
                if ($positions !== []) {
                    $lifted[$this->idOf($refusal)] ??= $refusal;
                    $more += $positions;
                }
            }
            $leftOut += $more;
        } while ($more !== []);

        foreach ($refused as $refusal) {
            unset($lifted[$this->idOf($refusal)]);
        }

        return ['lifted' => array_values($lifted), 'remaining' => $refused];
    }

    /**
     * @param array<int, FieldWriter> $writers
     * @param Refusal $refusal
     *
     * @return array<int, true> the writers of the refused key in the languages it is refused in
     */
    public function writersOf(array $writers, array $refusal): array
    {
        if ($refusal['key'] === null) {
            return [];
        }

        $positions = [];
        foreach ($writers as $position => $writer) {
            $field = $writer->field();
            if (
                $field->key === $refusal['field']
                && $field->storeKey?->id() === $refusal['key']->id()
                && in_array($field->language ?? self::DEFAULT_LANGUAGE, $refusal['languages'], true)
            ) {
                $positions[$position] = true;
            }
        }

        return $positions;
    }

    /**
     * @return list<Refusal> one per refused key, with the languages it is refused in; without a key where the store
     *     as a whole is refused
     */
    public function refused(Concrete $object): array
    {
        $refused = [];
        foreach ($this->fields as $field) {
            $store = $object->get($field);
            $definition = $object->getClass()->getFieldDefinition($field);
            if (!$store instanceof Classificationstore || !$definition instanceof Data\Classificationstore) {
                continue;
            }

            if ($this->holdsTooManyGroups($store, $definition)) {
                $refused[] = [
                    'field' => $field,
                    'key' => null,
                    'label' => $definition->getTitle() ?: $field,
                    'value' => '',
                    'error' => ValueError::TooManyGroups,
                    'languages' => [],
                ];
            }

            foreach (array_keys(array_filter($store->getActiveGroups())) as $groupId) {
                foreach ($this->keysOf($groupId) as $key) {
                    $refusal = $this->refusal($object, $store, $field, $definition->isLocalized(), $key);
                    if ($refusal !== null) {
                        $refused[] = $refusal;
                    }
                }
            }
        }

        return $refused;
    }

    /**
     * @param array<string, Classificationstore> $before
     * @param array<int, FieldWriter> $writers
     * @param array<int, true> $leftOut
     * @param array<int|string, mixed> $row
     */
    private function leaveOut(
        Concrete $object,
        array $before,
        array $writers,
        array $leftOut,
        int $index,
        array $row
    ): void {
        $stores = [];
        foreach (array_keys($leftOut) as $position) {
            $field = $writers[$position]->field();
            if ($field->storeKey !== null && isset($before[$field->key])) {
                $stores[$field->key] = true;
            }
        }

        foreach (array_keys($stores) as $store) {
            $object->set($store, clone $before[$store]);
        }
        foreach ($writers as $position => $writer) {
            $field = $writer->field();
            if ($field->storeKey !== null && isset($stores[$field->key]) && !isset($leftOut[$position])) {
                $writer->write($object, $index, $row);
            }
        }
    }

    /**
     * @param Refusal $refusal
     */
    private function idOf(array $refusal): string
    {
        return $refusal['field'] . '/' . $refusal['key']?->id();
    }

    /**
     * Pimcore counts every group of the list here, also one switched off.
     */
    private function holdsTooManyGroups(Classificationstore $store, Data\Classificationstore $definition): bool
    {
        $limit = (int) $definition->getMaxItems();

        return $limit > 0 && count($store->getActiveGroups()) > $limit;
    }

    /**
     * @return list<GroupKey>
     */
    private function keysOf(int $groupId): array
    {
        $this->keys[$groupId] ??= ($this->keysOfGroup)($groupId);

        return $this->keys[$groupId];
    }

    /**
     * @param GroupKey $key
     *
     * @return Refusal|null
     */
    private function refusal(
        Concrete $object,
        Classificationstore $store,
        string $field,
        bool $localized,
        array $key
    ): ?array {
        $languages = $localized ? [self::DEFAULT_LANGUAGE, ...$this->languages] : [self::DEFAULT_LANGUAGE];
        $refusedIn = [];
        $firstError = null;
        $firstValue = null;
        foreach ($languages as $language) {
            $value = $this->savedValue($object, $store, $key['key'], $language);
            $error = $this->error($object, $key['definition'], $value);
            if ($error !== null) {
                $refusedIn[] = $language;
                $firstError ??= $error;
                $firstValue ??= $value;
            }
        }
        if ($firstError === null) {
            return null;
        }

        return [
            'field' => $field,
            'key' => $key['key'],
            'label' => $localized ? $key['label'] . ' (' . implode(', ', $refusedIn) . ')' : $key['label'],
            'value' => ValueText::of($firstValue) ?? '',
            'error' => $firstError,
            'languages' => $refusedIn,
        ];
    }

    /**
     * A class that allows inheritance is checked on the value a read returns (with the default language and the
     * parents); another one on the value of exactly that language.
     */
    private function savedValue(Concrete $object, Classificationstore $store, StoreKey $key, string $language): mixed
    {
        if (!$object->getClass()->getAllowInherit()) {
            return $store->getItems()[$key->groupId][$key->keyId][$language] ?? null;
        }

        return Service::useInheritedValues(
            true,
            static fn (): mixed => $store->getLocalizedKeyValue($key->groupId, $key->keyId, $language, true)
        );
    }

    private function error(Concrete $object, Data $definition, mixed $value): ?ValueError
    {
        try {
            $definition->checkValidity($value, false, ['object' => $object]);
        } catch (\Exception) { // NOSONAR php:S2221 any exception of a check is a refusal, as in Pimcore's own check
            return $definition->getMandatory() && $definition->isEmpty($value)
                ? ValueError::Required
                : ValueError::Invalid;
        }

        return null;
    }
}
