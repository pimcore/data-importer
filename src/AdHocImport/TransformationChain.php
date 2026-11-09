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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\UserReferenceVisibility;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\OperatorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Model\User;

/**
 * Runs the transformations of one column on its values in the first rows, adds the conversion to the field's type and
 * says which transformations can come next.
 *
 * @internal
 */
final readonly class TransformationChain
{
    // operators log under their configuration's name; an ad hoc import has none
    private const string CONFIG_NAME = '';

    // what the transformations of one column can lead to; a field takes those of them its type is listed for
    private const array RESULT_TYPES = [
        TransformationDataTypeService::DEFAULT_TYPE,
        TransformationDataTypeService::NUMERIC,
        TransformationDataTypeService::BOOLEAN,
        TransformationDataTypeService::DEFAULT_ARRAY,
        TransformationDataTypeService::DATE,
        TransformationDataTypeService::DATE_ARRAY,
        TransformationDataTypeService::ASSET,
        TransformationDataTypeService::ASSET_ARRAY,
        TransformationDataTypeService::GALLERY,
        TransformationDataTypeService::DATA_OBJECT,
        TransformationDataTypeService::DATA_OBJECT_ARRAY,
    ];

    private const string DATE_FORMAT = 'Y-m-d';

    // asArray takes any input; only a text becomes a list of it, a loaded object or asset is no option value
    private const array CONVERSION_INPUTS = [
        'asArray' => [TransformationDataTypeService::DEFAULT_TYPE],
    ];

    // an empty cell stays empty, as in a Data Importer run that does not write empty values
    private const array CONVERSION_SETTINGS = [
        'numeric' => ['returnNullIfEmpty' => '1'],
    ];

    public function __construct(
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private DataTypeServiceInterface $dataTypeService,
        private TransformationDataTypeService $transformationDataTypes,
    ) {
    }

    /**
     * Per field of the class the result types it takes.
     *
     * @return array<string, list<string>>
     */
    public function acceptedTypes(string $classId): array
    {
        $accepted = [];
        foreach (self::RESULT_TYPES as $type) {
            $attributes = $this->dataTypeService->loadClassAttributes($classId, $type, false, true, false);
            foreach ($attributes->getAttributes() as $attribute) {
                $accepted[$attribute['key']][] = $type;
            }
        }

        return $accepted;
    }

    /**
     * The result types a field of this Pimcore data type takes, as for a key of a classification store.
     *
     * @return list<string>
     */
    public function acceptedTypesOf(string $dataType): array
    {
        return array_values(array_filter(
            self::RESULT_TYPES,
            fn (string $type): bool => in_array(
                $dataType,
                $this->transformationDataTypes->getPimcoreTypesByTransformationTargetType($type),
                true
            )
        ));
    }

    /**
     * Whether a column goes into a field taking these types without a transformation.
     *
     * @param list<string> $accepted
     */
    public function takesColumnAsIs(array $accepted): bool
    {
        return $this->conversionFor(TransformationDataTypeService::DEFAULT_TYPE, $accepted) !== false;
    }

    /**
     * @param list<?string> $values the column's values in the first rows
     * @param list<array{type: string, settings: array<string, string>}> $steps checked against the allow list
     * @param list<string> $accepted the result types the field takes
     * @param User $user loaded objects and assets the user may not see are not found
     *
     * @throws InvalidConfigurationException
     */
    public function run(array $values, array $steps, array $accepted, User $user): AdHocImportTransformationResponse
    {
        $operators = $this->operators($steps);
        [$type, $data, $failed, $stepResults] = $this->runSteps($operators, $steps, $values, $user);

        // what comes next takes the steps' result, not its conversion
        $offered = $type === null ? [] : $this->offered($type, $accepted);
        $conversion = $type === null ? false : $this->conversionFor($type, $accepted);
        $results = [];
        if ($conversion !== false) {
            [$type, $data, $failed] = $this->convert($conversion, $type, count($operators) + 1, $data, $failed, $user);
            $results = CellItems::cells($data, $failed);
        }

        return new AdHocImportTransformationResponse(
            CellItems::cells($values, array_fill(0, count($values), false)),
            $stepResults,
            $conversion === false ? null : $conversion,
            $type,
            $conversion !== false,
            $results,
            $offered
        );
    }

    /**
     * The column's values as its field takes them: the steps and the conversion to the field's type run on every
     * value. Null if they do not lead into the field.
     *
     * @param list<?string> $values
     * @param list<array{type: string, settings: array<string, string>}> $steps checked against the allow list
     * @param list<string> $accepted the result types the field takes
     * @param User $user loaded objects and assets the user may not see are not found
     *
     * @throws InvalidConfigurationException
     */
    public function convertColumn(array $values, array $steps, array $accepted, User $user): ?ConvertedColumn
    {
        $operators = $this->operators($steps);
        [$type, $data, $failed, , $lost] = $this->runSteps($operators, $steps, $values, $user);
        $conversion = $type === null ? false : $this->conversionFor($type, $accepted);
        if ($type === null || $conversion === false) {
            return null;
        }

        [, $converted, $failedConversion, $lost] = $this->convert(
            $conversion,
            $type,
            count($operators) + 1,
            $data,
            $failed,
            $user,
            $lost
        );

        return new ConvertedColumn($converted, $failed, $failedConversion, $conversion, $lost);
    }

    /**
     * @param list<mixed> $data
     * @param list<bool> $failed
     * @param list<bool> $lost
     *
     * @return array{0: string, 1: list<mixed>, 2: list<bool>, 3: list<bool>}
     *
     * @throws InvalidConfigurationException
     */
    private function convert(
        ?string $conversion,
        string $type,
        int $position,
        array $data,
        array $failed,
        User $user,
        array $lost = []
    ): array {
        if ($conversion === null) {
            return [$type, $data, $failed, $lost];
        }

        $converter = $this->operators([
            ['type' => $conversion, 'settings' => self::CONVERSION_SETTINGS[$conversion] ?? []],
        ])[0];
        $type = $converter->evaluateReturnType($type, $position);
        $failed = $this->unreadable($conversion, $data, $failed);
        [$data, $failed, $lost] = $this->process($converter, $data, $failed, $user, $lost, true);

        return [$type, $data, $failed, $lost];
    }

    /**
     * Runs the steps one after the other until one cannot take the result before it.
     *
     * @param list<OperatorInterface> $operators
     * @param list<array{type: string, settings: array<string, string>}> $steps
     * @param list<?string> $values
     *
     * @return array{0: ?string, 1: list<mixed>, 2: list<bool>, 3: list<array<string, mixed>>, 4: list<bool>}
     */
    private function runSteps(array $operators, array $steps, array $values, User $user): array
    {
        $type = TransformationDataTypeService::DEFAULT_TYPE;
        $data = $values;
        $failed = array_fill(0, count($values), false);
        $lost = $failed;
        $stepResults = [];
        foreach ($operators as $position => $operator) {
            $error = null;
            if ($type !== null) {
                try {
                    $this->checkStep($steps[$position], $type, $position + 1);
                    $type = $operator->evaluateReturnType($type, $position + 1);
                    $failed = $this->malformedIds($steps[$position], $data, $failed);
                    [$data, $failed, $lost] = $this->process(
                        $operator,
                        $data,
                        $failed,
                        $user,
                        $lost,
                        loads: $this->loads($steps[$position])
                    );
                } catch (InvalidConfigurationException $exception) {
                    $type = null;
                    $error = $exception->getMessage();
                }
            }

            $stepResults[] = [
                'type' => $steps[$position]['type'],
                'resultType' => $type,
                'error' => $error,
                'values' => $type === null ? [] : CellItems::cells($data, $failed),
            ];
        }

        return [$type, $data, $failed, $stepResults, $lost];
    }

    /**
     * @param array{type: string, settings: array<string, string>} $step
     *
     * @throws InvalidConfigurationException for an input or settings the operator would read wrongly
     */
    private function checkStep(array $step, string $type, int $position): void
    {
        if ($this->refuses($step['type'], $type)) {
            throw new InvalidConfigurationException(sprintf(
                "Unsupported input type '%s' for explode operator at transformation position %d",
                $type,
                $position
            ));
        }
        if ($step['type'] !== TransformationAllowList::MAP_VALUES) {
            return;
        }
        // the operator takes the counterpart at the same position; a missing one would clear the value
        $original = explode('|', $step['settings']['original'] ?? '');
        $converted = explode('|', $step['settings']['converted'] ?? '');
        if (count($original) !== count($converted)) {
            throw new InvalidConfigurationException(sprintf(
                'The lists of the map values operator at transformation position %d differ in length (%d and %d).',
                $position,
                count($original),
                count($converted)
            ));
        }
    }

    /**
     * The Data Importer's split nests the lists of a list, which no field and no step after it takes.
     */
    private function refuses(string $operatorType, string $type): bool
    {
        return $operatorType === TransformationAllowList::SPLIT
            && $type === TransformationDataTypeService::DEFAULT_ARRAY;
    }

    /**
     * Marks the rows whose value, or an item of it, is no ID: the loader would read `12abc` or `1,2` as the number
     * it starts with.
     *
     * @param array{type: string, settings: array<string, string>} $step
     * @param list<mixed> $data
     * @param list<bool> $failed
     *
     * @return list<bool>
     */
    private function malformedIds(array $step, array $data, array $failed): array
    {
        if (!in_array($step['type'], [TransformationAllowList::LOAD_OBJECT, TransformationAllowList::LOAD_ASSET], true)
            || ($step['settings']['loadStrategy'] ?? null) !== 'id') {
            return $failed;
        }

        return $this->markRows(
            $data,
            $failed,
            static fn (string $value): bool => ctype_digit(trim($value))
        );
    }

    /**
     * Marks the rows whose value, or an item of it, the conversion would read as something else: a number or yes/no
     * it cannot read would become 0 or false, a day past the month's end a day of the next month, instead of failing.
     *
     * @param list<mixed> $data
     * @param list<bool> $failed
     *
     * @return list<bool>
     */
    private function unreadable(string $conversion, array $data, array $failed): array
    {
        $readable = static function (string $value) use ($conversion): bool {
            if ($conversion !== 'date') {
                return match ($conversion) {
                    'numeric' => is_numeric($value),
                    'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,
                    default => true,
                };
            }
            $date = date_create_immutable_from_format('!' . self::DATE_FORMAT, $value);
            // a warning names a day the month does not have, which the parser would carry into the next month
            $errors = date_get_last_errors();

            return $date !== false && ($errors === false || $errors['warning_count'] === 0);
        };

        return $this->markRows($data, $failed, $readable);
    }

    /**
     * Marks the rows with a text, or a list with a text, that is not empty and not readable.
     *
     * @param list<mixed> $data
     * @param list<bool> $failed
     * @param callable(string): bool $readable
     *
     * @return list<bool>
     */
    private function markRows(array $data, array $failed, callable $readable): array
    {
        foreach ($data as $row => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_string($item) && $item !== '' && !$readable($item)) {
                    $failed[$row] = true;

                    break;
                }
            }
        }

        return $failed;
    }

    /**
     * The operator that converts a result of this type into the field, null if none is needed, false if none can.
     *
     * @param list<string> $accepted
     */
    private function conversionFor(string $type, array $accepted): string|null|false
    {
        if (in_array($type, $accepted, true)) {
            return null;
        }

        foreach (TransformationAllowList::CONVERSIONS as $conversion) {
            $takes = self::CONVERSION_INPUTS[$conversion] ?? null;
            if ($takes !== null && !in_array($type, $takes, true)) {
                continue;
            }
            if (in_array($this->resultOf($conversion, $type), $accepted, true)) {
                return $conversion;
            }
        }

        return false;
    }

    /**
     * The transformations that take a result of this type and after which the field can still be reached.
     *
     * @param list<string> $accepted
     *
     * @return list<string>
     */
    private function offered(string $type, array $accepted): array
    {
        return array_values(array_filter(
            TransformationAllowList::types(),
            function (string $operator) use ($type, $accepted): bool {
                $next = $this->resultOf($operator, $type);

                return $next !== null && $this->reaches($next, $accepted);
            }
        ));
    }

    /**
     * @param list<string> $accepted
     * @param array<string, true> $seen
     */
    private function reaches(string $type, array $accepted, array $seen = []): bool
    {
        if ($this->conversionFor($type, $accepted) !== false) {
            return true;
        }
        $seen[$type] = true;

        foreach (TransformationAllowList::types() as $operator) {
            $next = $this->resultOf($operator, $type);
            if ($next !== null && !isset($seen[$next]) && $this->reaches($next, $accepted, $seen)) {
                return true;
            }
        }

        return false;
    }

    private function resultOf(string $operatorType, string $type): ?string
    {
        if ($this->refuses($operatorType, $type)) {
            return null;
        }
        // a conversion takes no settings
        $step = in_array($operatorType, TransformationAllowList::types(), true)
            ? TransformationAllowList::step(['type' => $operatorType], 0)
            : ['type' => $operatorType, 'settings' => []];

        try {
            return $this->operators([$step])[0]->evaluateReturnType($type);
        } catch (InvalidConfigurationException) {
            return null;
        }
    }

    /**
     * @param list<array{type: string, settings: array<string, string>}> $steps
     *
     * @return list<OperatorInterface>
     *
     * @throws InvalidConfigurationException
     */
    private function operators(array $steps): array
    {
        return array_values($this->mappingConfigurationFactory->loadMappingConfigurationItem(
            self::CONFIG_NAME,
            ['transformationPipeline' => $steps],
            true
        )->getTransformationPipeline());
    }

    /**
     * Runs one step on every row that has not failed yet.
     *
     * @param list<mixed> $data
     * @param list<bool> $failed
     * @param list<bool> $lost the rows whose value lost an element: one the user may not see, or one a load step found
     *                         nothing for
     * @param bool $keepEmpty an empty cell stays empty instead of becoming a No, as in a run that writes no empty value
     * @param bool $loads the step loads objects or assets
     *
     * @return array{0: list<mixed>, 1: list<bool>, 2: list<bool>}
     */
    private function process(
        OperatorInterface $operator,
        array $data,
        array $failed,
        User $user,
        array $lost,
        bool $keepEmpty = false,
        bool $loads = false
    ): array {
        $references = new UserReferenceVisibility($user);
        foreach ($data as $row => $value) {
            if ($failed[$row]) {
                continue;
            }
            if ($keepEmpty && ($value === null || $value === '')) {
                $data[$row] = null;

                continue;
            }

            try {
                // a dry run logs no misses; a loaded element the user may not see counts as not found
                $loaded = $operator->process($value, true);
                $data[$row] = $references->visibleIn($loaded);
                // a reference that finds nothing fails like a hidden one, so the two cannot be told apart
                $lost[$row] = ($lost[$row] ?? false)
                    || $references->hidesAnElementIn($loaded)
                    || ($loads && self::findsLess($value, $loaded));
            } catch (\Exception) { // NOSONAR php:S2221 an operator may fail on a value from the file; an Error is a bug
                $data[$row] = null;
                $failed[$row] = true;
            }
        }

        return [$data, $failed, $lost];
    }

    /**
     * @param array{type: string, settings: array<string, string>} $step
     */
    private function loads(array $step): bool
    {
        return in_array($step['type'], [TransformationAllowList::LOAD_OBJECT, TransformationAllowList::LOAD_ASSET], true);
    }

    /**
     * Whether a load found fewer elements than the value names.
     */
    private static function findsLess(mixed $value, mixed $loaded): bool
    {
        $named = array_filter(
            is_array($value) ? $value : [$value],
            static fn (mixed $item): bool => $item !== null && (!is_scalar($item) || trim((string) $item) !== '')
        );
        $found = array_filter(is_array($loaded) ? $loaded : [$loaded], static fn (mixed $item): bool => $item !== null);

        return count($found) < count($named);
    }
}
