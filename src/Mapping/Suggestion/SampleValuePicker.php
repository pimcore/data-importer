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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion;

use Random\Randomizer;

/**
 * Draws the few values of a column that may go to the agent's model. No rows leave here: each column is drawn on its
 * own, in its own order, so that the values of different columns form no record.
 *
 * @internal
 */
final readonly class SampleValuePicker
{
    private const int SAMPLES_PER_COLUMN = 3;

    private const int MAX_VALUE_LENGTH = 50;

    // the values of one row alone would be a record; a row without a value in an asked column does not count
    private const int MIN_ROWS_WITH_VALUES = 2;

    private const int MAX_ROWS = 10000;

    public function __construct(private Randomizer $randomizer = new Randomizer())
    {
    }

    /**
     * @param iterable<array<int|string, mixed>> $rows
     * @param list<string> $columnIndexes the columns to draw for
     *
     * @return array<string, list<string>> the values per column, without a column that has none
     */
    public function pick(iterable $rows, array $columnIndexes): array
    {
        $samples = [];
        $seen = [];
        $rowsOf = [];
        $rowCount = 0;
        $rowsWithValues = 0;

        foreach ($rows as $row) {
            if ($rowCount === self::MAX_ROWS) {
                break;
            }
            $hasValue = false;

            foreach ($columnIndexes as $index) {
                $value = $this->cellValue($row[$index] ?? null);
                if ($value === null) {
                    continue;
                }

                $hasValue = true;
                $rowsOf[$index] = $this->withRow($rowsOf[$index] ?? '', $rowCount);
                $seen[$index] = ($seen[$index] ?? 0) + 1;
                $samples[$index] = $this->keep($samples[$index] ?? [], $value, $seen[$index]);
            }

            $rowCount++;
            $rowsWithValues += $hasValue ? 1 : 0;
        }

        if ($rowsWithValues < self::MIN_ROWS_WITH_VALUES) {
            return [];
        }

        $samples = array_map(static fn (array $values): array => array_values(array_unique($values)), $samples);
        $samples = array_diff_key($samples, $this->singleValuesSharingARow($samples, $rowsOf));

        return array_map(fn (array $values): array => $this->randomizer->shuffleArray($values), $samples);
    }

    /**
     * Columns that send one value only, where another such column has a value in the same row: their values are
     * certain to belong together, so together they would be part of that row's record. A column that sends several
     * values leaves open which of them goes with another column's value.
     *
     * @param array<string, list<string>> $samples
     * @param array<string, string> $rowsOf the rows with a value, per column, one bit per row
     *
     * @return array<string, true>
     */
    private function singleValuesSharingARow(array $samples, array $rowsOf): array
    {
        $single = array_keys(array_filter($samples, static fn (array $values): bool => count($values) === 1));

        $dropped = [];
        foreach ($single as $position => $index) {
            foreach (array_slice($single, $position + 1) as $other) {
                if (trim($rowsOf[$index] & $rowsOf[$other], "\0") !== '') {
                    $dropped[$index] = true;
                    $dropped[$other] = true;
                }
            }
        }

        return $dropped;
    }

    private function withRow(string $rows, int $row): string
    {
        $byte = intdiv($row, 8);
        $rows = str_pad($rows, $byte + 1, "\0");
        $rows[$byte] = chr(ord($rows[$byte]) | (1 << ($row % 8)));

        return $rows;
    }

    /**
     * Reservoir sampling: every value of the column has the same chance, whatever the length of the file.
     *
     * @param array<int, string> $kept
     *
     * @return array<int, string>
     */
    private function keep(array $kept, string $value, int $seen): array
    {
        if ($seen <= self::SAMPLES_PER_COLUMN) {
            $kept[] = $value;

            return $kept;
        }

        $slot = $this->randomizer->getInt(0, $seen - 1);
        if ($slot < self::SAMPLES_PER_COLUMN) {
            $kept[$slot] = $value;
        }

        return $kept;
    }

    private function cellValue(mixed $cell): ?string
    {
        if (!is_string($cell) && !is_int($cell) && !is_float($cell)) {
            return null;
        }

        $value = trim((string) $cell);

        return $value === '' ? null : mb_substr($value, 0, self::MAX_VALUE_LENGTH);
    }
}
