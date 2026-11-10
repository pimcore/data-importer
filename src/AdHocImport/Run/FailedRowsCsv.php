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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\CsvText;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SpreadsheetCell;

/**
 * The failed rows of a run as the CSV file its user downloads.
 *
 * @internal
 */
final class FailedRowsCsv
{
    /**
     * @param list<string> $columns
     * @param list<array{row: int, message: string, values: list<string>}> $rows
     */
    public static function of(array $columns, array $rows, string $rowHeading, string $errorHeading): string
    {
        $lines = [[$rowHeading, ...array_map(SpreadsheetCell::safe(...), $columns), $errorHeading]];
        foreach ($rows as $row) {
            $lines[] = [
                $row['row'],
                ...array_map(SpreadsheetCell::safe(...), $row['values']),
                SpreadsheetCell::safe($row['message']),
            ];
        }

        return CsvText::of($lines);
    }
}
