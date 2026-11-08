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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\FieldAccess;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ValueText;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\TextMatchValue;
use Pimcore\Model\DataObject\ClassDefinition\Data\Multiselect;
use Pimcore\Model\DataObject\ClassDefinition\Data\Select;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\Service;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Tool;

/**
 * Finds out what an import would do with every row of a file: which object it updates or creates, what changes and
 * which rows or values it cannot take. Saves nothing: the objects change in memory only, and a save tried meanwhile
 * fails.
 *
 * @internal
 *
 * @phpstan-import-type Refusal from StoreGroupCheck
 */
final readonly class DryRunner
{
    // the objects a file changes in memory are dropped from the runtime cache now and then, so they stay few
    private const int ROWS_BETWEEN_CLEANUPS = 100;

    public function __construct(
        private MappingApplicationScope $scope,
        private DryRunMessages $messages,
    ) {
    }

    /**
     * @return list<DryRunRow> in the order of the file
     */
    public function run(DryRunPlan $plan): array
    {
        return $this->scope->run(null, function () use ($plan): array {
            $storeCheck = $this->storeCheck($plan);
            $results = [];
            $index = 0;
            foreach ($plan->rows as $row) {
                $results[] = $this->checkRow($plan, $storeCheck, $row, $index, $plan->firstRowNumber + $index);
                ++$index;
                if ($index % self::ROWS_BETWEEN_CLEANUPS === 0) {
                    \Pimcore::collectGarbage();
                }
            }
            \Pimcore::collectGarbage();

            return $results;
        });
    }

    /**
     * @param array<int|string, mixed> $row
     */
    private function checkRow(
        DryRunPlan $plan,
        StoreGroupCheck $storeCheck,
        array $row,
        int $index,
        int $rowNumber
    ): DryRunRow {
        $match = $plan->matcher->match($row, $rowNumber);
        $object = $match->element;
        if (!$object instanceof Concrete) {
            return $this->errorRow($plan, $match, $row, $this->messages->rowError(
                $match,
                $plan->matchField,
                $plan->targetFolder,
                $plan->newObjectFolder,
                $plan->user
            ), $rowNumber);
        }

        // what an object the user may not see holds, or where it lies, is not told
        if ($match->outcome === RowMatchOutcome::Existing && !$object->isAllowed('view', $plan->user)) {
            return $this->errorRow(
                $plan,
                $match,
                $row,
                $this->messages->notViewable($plan->matchField, $match->matchValue, $plan->user),
                $rowNumber
            );
        }

        $isNew = $match->outcome === RowMatchOutcome::New;
        $shownAt = array_flip($plan->shown);
        $values = [];
        $invalid = [];
        $reportedStoreKeys = [];
        $leftOut = [];
        $stores = $storeCheck->stores($object);
        foreach ($plan->writers as $position => $writer) {
            $field = $writer->field();
            $old = $isNew ? null : $this->read($object, $field);
            $error = $writer->write($object, $index, $row) ?? $this->validate($object, $field);
            if ($error !== null) {
                $leftOut[$position] = true;
                $value = $writer->fileValue($row) ?? '';
                $invalid[] = [
                    'field' => $shownAt[$position] ?? null,
                    'label' => $field->label,
                    'value' => $value,
                    'message' => $this->messages->valueError($error, $field->label, $value, $plan->user),
                ];
                if ($field->storeKey !== null) {
                    $reportedStoreKeys[$field->key . '/' . $field->storeKey->id()] = true;
                }
            }
            $values[$position] = [
                'old' => $old,
                'new' => $error === null ? $this->read($object, $field) : $writer->fileValue($row),
            ];
        }

        $blocked = false;
        if ($object->isPublished()) {
            $refusals = $storeCheck->refusedWithout($object, $stores, $plan->writers, $leftOut, $index, $row);
            $blocked = $refusals['remaining'] !== [];
            array_push(
                $invalid,
                ...$this->refusedEntries(
                    $plan,
                    $storeCheck,
                    [...$refusals['lifted'], ...$refusals['remaining']],
                    $reportedStoreKeys,
                    $shownAt
                )
            );
        }

        return new DryRunRow(
            $rowNumber,
            $match->matchValue,
            $isNew ? DryRunRow::CREATE : DryRunRow::UPDATE,
            null,
            array_map(static fn (int $position): array => $values[$position], $plan->shown),
            $invalid,
            $blocked
        );
    }

    /**
     * A row that means no object: it shows only what the file says.
     *
     * @param array<int|string, mixed> $row
     */
    private function errorRow(DryRunPlan $plan, RowMatch $match, array $row, string $error, int $rowNumber): DryRunRow
    {
        return new DryRunRow(
            $rowNumber,
            $match->matchValue,
            null,
            $error,
            array_map(
                static fn (int $position): array => [
                    'old' => null,
                    'new' => $plan->writers[$position]->fileValue($row),
                ],
                $plan->shown
            )
        );
    }

    /**
     * @param list<Refusal> $refusals
     * @param array<string, true> $reportedStoreKeys the store keys a column has already reported
     * @param array<int, int> $shownAt
     *
     * @return list<array{field: int|null, label: string, value: string, message: string}>
     */
    private function refusedEntries(
        DryRunPlan $plan,
        StoreGroupCheck $storeCheck,
        array $refusals,
        array $reportedStoreKeys,
        array $shownAt
    ): array {
        $entries = [];
        foreach ($refusals as $refused) {
            if (!$this->isReported($refused, $reportedStoreKeys)) {
                $entries[] = $this->refusedEntry($plan, $storeCheck, $refused, $shownAt);
            }
        }

        return $entries;
    }

    /**
     * @param array{field: string, key: StoreKey|null} $refused
     * @param array<string, true> $reportedStoreKeys the store keys a column has already reported
     */
    private function isReported(array $refused, array $reportedStoreKeys): bool
    {
        return $refused['key'] !== null && isset($reportedStoreKeys[$refused['field'] . '/' . $refused['key']->id()]);
    }

    /**
     * @param array{
     *     field: string,
     *     key: StoreKey|null,
     *     label: string,
     *     value: string,
     *     error: ValueError,
     *     languages: list<string>
     * } $refused
     * @param array<int, int> $shownAt
     *
     * @return array{field: int|null, label: string, value: string, message: string}
     */
    private function refusedEntry(
        DryRunPlan $plan,
        StoreGroupCheck $storeCheck,
        array $refused,
        array $shownAt
    ): array {
        $position = array_key_first($storeCheck->writersOf($plan->writers, $refused));

        return [
            'field' => $position === null ? null : ($shownAt[$position] ?? null),
            'label' => $refused['label'],
            'value' => $refused['value'],
            'message' => $this->messages->valueError(
                $refused['error'],
                $refused['label'],
                $refused['value'],
                $plan->user
            ),
        ];
    }

    private function storeCheck(DryRunPlan $plan): StoreGroupCheck
    {
        $stores = [];
        foreach ($plan->writers as $writer) {
            $field = $writer->field();
            if ($field->storeKey !== null && $field->key !== null) {
                $stores[$field->key] = true;
            }
        }

        return new StoreGroupCheck(array_keys($stores), Tool::getValidLanguages(), $this->keysOfGroup(...));
    }

    /**
     * The keys of a group as Pimcore's check of a save sees them: with the group's mandatory mark.
     *
     * @return list<array{key: StoreKey, label: string, definition: \Pimcore\Model\DataObject\ClassDefinition\Data}>
     */
    private function keysOfGroup(int $groupId): array
    {
        $group = GroupConfig::getById($groupId);
        if ($group === null) {
            return [];
        }

        $keys = [];
        foreach ($group->getRelations() as $relation) {
            $definition = Service::getFieldDefinitionFromJson(
                (array) json_decode($relation->getDefinition(), true),
                $relation->getType()
            );
            if ($definition === null) {
                continue;
            }
            if ($relation->isMandatory()) {
                $definition->setMandatory(true);
            }
            $keys[] = [
                'key' => new StoreKey($groupId, $relation->getKeyId()),
                'label' => $group->getName() . ' › ' . $relation->getName(),
                'definition' => $definition,
            ];
        }

        return $keys;
    }

    private function read(Concrete $object, ImportField $field): ?string
    {
        return $field->key === null
            ? null
            : ValueText::of(FieldAccess::read($object, $field->key, $field->language, $field->storeKey));
    }

    /**
     * What saving the object would refuse about the field's new value. Pimcore checks an unpublished object, as a new
     * one is, without its mandatory fields and lengths.
     */
    private function validate(Concrete $object, ImportField $field): ?ValueError
    {
        $definition = $field->key === null
            ? null
            : FieldAccess::definition($object->getClass(), $field->key, $field->storeKey);
        if ($definition === null) {
            return null;
        }

        $value = FieldAccess::read($object, $field->key, $field->language, $field->storeKey);
        if ($definition->isEmpty($value)) {
            // a store key's mark is its group's, and the group is checked once the row is written
            return $field->storeKey === null && $definition->getMandatory() && $object->isPublished()
                ? ValueError::Required
                : null;
        }

        if ($definition instanceof Select || $definition instanceof Multiselect) {
            return $this->areOptions($value, $definition) ? null : ValueError::NotAnOption;
        }

        try {
            $definition->checkValidity($value, !$object->isPublished(), ['object' => $object]);
        } catch (ValidationException) {
            return ValueError::Invalid;
        }

        return null;
    }

    /**
     * Not the field's checkValidity(), which looks at the options only if the field enforces them.
     */
    private function areOptions(mixed $value, Select|Multiselect $definition): bool
    {
        if ($definition instanceof Select) {
            return !is_string($value) || TextMatchValue::fits($value, $definition);
        }

        $options = $definition->getOptions();
        if (!is_array($value) || !$options || $definition->dynamicOptions || !$definition->useConfiguredOptions()) {
            return true;
        }

        $allowed = array_map(strval(...), array_column($options, 'value'));
        foreach ($value as $item) {
            if (!in_array((string) $item, $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
