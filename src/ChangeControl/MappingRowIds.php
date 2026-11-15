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

namespace Pimcore\Bundle\DataImporterBundle\ChangeControl;

use function array_diff;
use function array_keys;
use function is_array;
use function is_string;
use function json_encode;

/**
 * The Studio editor mints a `mappingId` for every mapping row stored without one on any save,
 * so a review compares mapping rows without it and an approval puts the stored ids back.
 *
 * @internal
 */
final class MappingRowIds
{
    private const string SECTION = 'mappingConfig';

    private const string ID = 'mappingId';

    /**
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    public static function dropped(array $state): array
    {
        $rows = $state[self::SECTION] ?? null;
        if (!is_array($rows)) {
            return $state;
        }

        foreach ($rows as $i => $row) {
            if (is_array($row)) {
                unset($rows[$i][self::ID]);
            }
        }
        $state[self::SECTION] = $rows;

        return $state;
    }

    /**
     * Each approved row takes the id of the stored row it is: the same row, else the one with
     * its label and source columns, else the one at its position. A row matching none is new.
     *
     * @param array<string, mixed> $approved
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public static function restored(array $approved, array $stored): array
    {
        $rows = $approved[self::SECTION] ?? null;
        $storedRows = $stored[self::SECTION] ?? null;
        if (!is_array($rows) || !is_array($storedRows)) {
            return $approved;
        }

        // an id the approved state still carries is that row's; never hand it out twice
        $free = array_diff(self::ids($storedRows), self::ids($rows));
        foreach ([false, true] as $bySource) {
            foreach ($rows as $i => $row) {
                $at = self::match($row, $storedRows, $free, $bySource);
                if ($at !== null) {
                    $rows[$i][self::ID] = $free[$at];
                    unset($free[$at]);
                }
            }
        }
        foreach ($rows as $i => $row) {
            if (is_array($row) && !isset($row[self::ID]) && isset($free[$i])) {
                $rows[$i][self::ID] = $free[$i];
                unset($free[$i]);
            }
        }
        $approved[self::SECTION] = $rows;

        return $approved;
    }

    /**
     * @param array<mixed> $rows
     *
     * @return array<int|string, string> position => id
     */
    private static function ids(array $rows): array
    {
        $ids = [];
        foreach ($rows as $at => $row) {
            $id = is_array($row) ? ($row[self::ID] ?? null) : null;
            if (is_string($id) && $id !== '') {
                $ids[$at] = $id;
            }
        }

        return $ids;
    }

    /**
     * The position of the free stored row an approved row without an id is, or null.
     *
     * @param array<mixed> $storedRows
     * @param array<int|string, string> $free
     */
    private static function match(mixed $row, array $storedRows, array $free, bool $bySource): int|string|null
    {
        if (!is_array($row) || isset($row[self::ID])) {
            return null;
        }
        $key = self::key($row, $bySource);
        foreach (array_keys($free) as $at) {
            if (is_array($storedRows[$at]) && self::key($storedRows[$at], $bySource) === $key) {
                return $at;
            }
        }

        return null;
    }

    /**
     * The whole row without its id, or only its label and source columns.
     *
     * @param array<mixed> $row
     */
    private static function key(array $row, bool $bySource): mixed
    {
        if ($bySource) {
            return json_encode([$row['label'] ?? null, $row['dataSourceIndex'] ?? null]) ?: '';
        }
        unset($row[self::ID]);

        return KeySorted::of($row);
    }
}
