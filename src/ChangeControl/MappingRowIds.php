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

        /** @var array<int|string, string> $free stored position => id not given out yet */
        $free = [];
        foreach ($storedRows as $at => $row) {
            $id = is_array($row) ? ($row[self::ID] ?? null) : null;
            if (is_string($id) && $id !== '') {
                $free[$at] = $id;
            }
        }
        foreach ($rows as $row) {
            // an id the approved state still carries is that row's; never hand it out twice
            $id = is_array($row) ? ($row[self::ID] ?? null) : null;
            if (is_string($id) && $id !== '') {
                foreach ($free as $at => $freeId) {
                    if ($freeId === $id) {
                        unset($free[$at]);
                    }
                }
            }
        }

        $keys = [self::sameRow(...), self::sameSource(...)];
        foreach ($keys as $key) {
            foreach ($rows as $i => $row) {
                if (!is_array($row) || isset($row[self::ID])) {
                    continue;
                }
                foreach ($free as $at => $id) {
                    if (is_array($storedRows[$at]) && $key($storedRows[$at]) === $key($row)) {
                        $rows[$i][self::ID] = $id;
                        unset($free[$at]);

                        break;
                    }
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
     * @param array<mixed> $row
     */
    private static function sameRow(array $row): mixed
    {
        unset($row[self::ID]);

        return KeySorted::of($row);
    }

    /**
     * @param array<mixed> $row
     */
    private static function sameSource(array $row): string
    {
        return json_encode([$row['label'] ?? null, $row['dataSourceIndex'] ?? null]) ?: '';
    }
}
