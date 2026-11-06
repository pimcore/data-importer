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

    // the values of one row alone would be a record
    private const int MIN_ROWS = 2;

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
        $rowCount = 0;

        foreach ($rows as $row) {
            if ($rowCount === self::MAX_ROWS) {
                break;
            }
            $rowCount++;

            foreach ($columnIndexes as $index) {
                $value = $this->cellValue($row[$index] ?? null);
                if ($value === null) {
                    continue;
                }

                $seen[$index] = ($seen[$index] ?? 0) + 1;
                $samples[$index] = $this->keep($samples[$index] ?? [], $value, $seen[$index]);
            }
        }

        if ($rowCount < self::MIN_ROWS) {
            return [];
        }

        return array_map(
            fn (array $values): array => $this->randomizer->shuffleArray(array_values(array_unique($values))),
            $samples
        );
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
