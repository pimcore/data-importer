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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch;

use Pimcore\Contracts\ChangeControl\DataObjectChange;
use Pimcore\Contracts\ChangeControl\ElementRef;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\EqualComparisonInterface;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Localizedfield;
use Pimcore\Model\DataObject\Objectbrick;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Normalizer\NormalizerInterface;
use Pimcore\Tool;

/**
 * What a row did to its object, as the change a branch records: every value that differs from the object as it is
 * stored, an emptied one as null, so that a value the mapping, a transformation or a listener set gets in and
 * nothing else does.
 *
 * @internal
 */
final readonly class ObjectChanges
{
    // they hold no value of their own, or one the branch has no place for
    // the definitions as stored: what an editor adds to them is no field of the object
    private const array AS_STORED = ['suppressEnrichment' => true];

    private const array NOT_RECORDED = [
        Data\Fieldcollections::class,
        Data\Block::class,
        Data\ReverseObjectRelation::class,
        Data\CalculatedValue::class,
    ];

    /**
     * @param Concrete $before the object as it is stored, or a new one of its class for one the row creates
     * @param string|null $token the branch's name for an object the row creates; null for one that exists
     */
    public function changeOf(Concrete $object, Concrete $before, ?string $token): DataObjectChange
    {
        $change = $token === null
            ? DataObjectChange::of((int) $object->getId())
            : DataObjectChange::create(
                $token,
                (string) $object->getClassName(),
                (int) $object->getParentId(),
                (string) $object->getKey()
            );

        // the values the object holds itself: neither a parent's nor another language's
        $fallback = Localizedfield::getGetFallbackValues();
        Localizedfield::setGetFallbackValues(false);

        try {
            return DataObject\Service::useInheritedValues(
                false,
                fn (): DataObjectChange => $this->withValues($change, $object, $before)
            );
        } finally {
            Localizedfield::setGetFallbackValues($fallback);
        }
    }

    private function withValues(DataObjectChange $change, Concrete $after, Concrete $before): DataObjectChange
    {
        foreach ($after->getClass()->getFieldDefinitions(self::AS_STORED) as $name => $definition) {
            $change = match (true) {
                $definition instanceof Data\Localizedfields
                    => $this->localized($change, $definition, $after, $before),
                $definition instanceof Data\Objectbricks
                    => $this->bricks($change, (string) $name, $definition, $after, $before),
                $definition instanceof Data\Classificationstore
                    => $this->store($change, (string) $name, $after, $before),
                $this->isRecorded($definition) => $this->field($change, (string) $name, $definition, $after, $before),
                default => $change,
            };
        }

        return $change;
    }

    private function field(
        DataObjectChange $change,
        string $name,
        Data $definition,
        Concrete $after,
        Concrete $before
    ): DataObjectChange {
        $value = $after->get($name);

        return self::differs($definition, $before->get($name), $value)
            ? $change->withValue($name, self::recordable($value))
            : $change;
    }

    private function localized(
        DataObjectChange $change,
        Data\Localizedfields $definition,
        Concrete $after,
        Concrete $before
    ): DataObjectChange {
        foreach ($definition->getFieldDefinitions(self::AS_STORED) as $name => $field) {
            if (!$this->isRecorded($field)) {
                continue;
            }
            foreach (Tool::getValidLanguages() as $language) {
                $value = $after->get((string) $name, $language);
                $old = $before->get((string) $name, $language);
                if (self::differs($field, $old, $value)) {
                    $change = $change->withLocalizedValue((string) $name, $language, self::recordable($value));
                }
            }
        }

        return $change;
    }

    private function bricks(
        DataObjectChange $change,
        string $name,
        Data\Objectbricks $definition,
        Concrete $after,
        Concrete $before
    ): DataObjectChange {
        $afterBricks = $after->get($name);
        $beforeBricks = $before->get($name);
        foreach ($definition->getAllowedTypes() as $type) {
            $brick = $afterBricks instanceof Objectbrick ? $afterBricks->get($type) : null;
            if (!$brick instanceof Objectbrick\Data\AbstractData || $brick->getDoDelete()) {
                continue;
            }
            $old = $beforeBricks instanceof Objectbrick ? $beforeBricks->get($type) : null;
            $old = $old instanceof Objectbrick\Data\AbstractData ? $old : null;
            $change = $this->brick($change, $name, $type, $brick, $old);
        }

        return $change;
    }

    private function brick(
        DataObjectChange $change,
        string $bricksField,
        string $type,
        Objectbrick\Data\AbstractData $brick,
        ?Objectbrick\Data\AbstractData $old
    ): DataObjectChange {
        foreach ($brick->getDefinition()->getFieldDefinitions(self::AS_STORED) as $name => $field) {
            if ($field instanceof Data\Localizedfields) {
                $change = $this->localizedBrick($change, $bricksField, $type, $field, $brick, $old);

                continue;
            }
            if (!$this->isRecorded($field)) {
                continue;
            }
            $value = $brick->get((string) $name);
            if (self::differs($field, $old?->get((string) $name), $value)) {
                $change = $change->withBrickValue($bricksField, $type, (string) $name, self::recordable($value));
            }
        }

        return $change;
    }

    private function localizedBrick(
        DataObjectChange $change,
        string $bricksField,
        string $type,
        Data\Localizedfields $definition,
        Objectbrick\Data\AbstractData $brick,
        ?Objectbrick\Data\AbstractData $old
    ): DataObjectChange {
        foreach ($definition->getFieldDefinitions(self::AS_STORED) as $name => $field) {
            if (!$this->isRecorded($field)) {
                continue;
            }
            foreach (Tool::getValidLanguages() as $language) {
                $value = $brick->get((string) $name, $language);
                if (self::differs($field, $old?->get((string) $name, $language), $value)) {
                    $change = $change->withBrickValue(
                        $bricksField,
                        $type,
                        (string) $name,
                        self::recordable($value),
                        $language
                    );
                }
            }
        }

        return $change;
    }

    private function store(DataObjectChange $change, string $name, Concrete $after, Concrete $before): DataObjectChange
    {
        $afterStore = $after->get($name);
        $beforeStore = $before->get($name);
        $afterItems = $afterStore instanceof Classificationstore ? $afterStore->getItems() : [];
        $beforeItems = $beforeStore instanceof Classificationstore ? $beforeStore->getItems() : [];

        // a value the row removed is recorded as null, as an emptied field is
        foreach (self::places($beforeItems, $afterItems) as [$groupId, $keyId, $language]) {
            $value = $afterItems[$groupId][$keyId][$language] ?? null;
            if (!self::differs(null, $beforeItems[$groupId][$keyId][$language] ?? null, $value)) {
                continue;
            }
            $group = Classificationstore\GroupConfig::getById($groupId)?->getName();
            $key = Classificationstore\KeyConfig::getById($keyId)?->getName();
            if ($group !== null && $key !== null) {
                $change = $change->withClassificationValue($name, $group, $key, self::recordable($value), $language);
            }
        }

        return $change;
    }

    /**
     * @param array<array-key, array<array-key, array<array-key, mixed>>> ...$itemSets
     *
     * @return array<string, array{0: int, 1: int, 2: string}> each group, key and language any of them holds
     */
    private static function places(array ...$itemSets): array
    {
        $places = [];
        foreach ($itemSets as $items) {
            foreach ($items as $groupId => $keys) {
                foreach ($keys as $keyId => $languages) {
                    foreach (array_keys($languages) as $language) {
                        $places[$groupId . '/' . $keyId . '/' . $language]
                            = [(int) $groupId, (int) $keyId, (string) $language];
                    }
                }
            }
        }

        return $places;
    }

    private function isRecorded(Data $definition): bool
    {
        foreach (self::NOT_RECORDED as $kind) {
            if ($definition instanceof $kind) {
                return false;
            }
        }

        return true;
    }

    /**
     * What cannot be compared counts as changed: the merge leaves out an object that already holds it.
     */
    private static function differs(?Data $definition, mixed $before, mixed $after): bool
    {
        try {
            if ($definition instanceof EqualComparisonInterface) {
                return !$definition->isEqual($before, $after);
            }
            if ($definition instanceof NormalizerInterface) {
                return $definition->normalize($before) != $definition->normalize($after);
            }
        } catch (\Throwable) { // NOSONAR php:S2221 a comparison that fails, e.g. on relations with metadata, counts as a change
            return true;
        }

        return is_object($before) || is_object($after) || $before !== $after;
    }

    /**
     * A relation names its targets by their id; every other value is what the field's setter takes.
     */
    private static function recordable(mixed $value): mixed
    {
        if ($value instanceof ElementInterface) {
            return self::ref($value);
        }
        if (is_array($value) && $value !== [] && array_is_list($value)
            && array_all($value, static fn (mixed $each): bool => $each instanceof ElementInterface)) {
            return array_map(self::ref(...), $value);
        }

        return $value;
    }

    private static function ref(ElementInterface $element): ElementRef
    {
        $id = (int) $element->getId();

        return match (true) {
            $element instanceof Asset => ElementRef::asset($id),
            $element instanceof Document => ElementRef::document($id),
            default => ElementRef::object($id),
        };
    }
}
