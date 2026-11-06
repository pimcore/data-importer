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

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Keeps no cell and notes the last row and the last column that have one.
 *
 * @internal
 */
final class SheetExtentReadFilter implements IReadFilter
{
    private int $lastRow = 0;

    private int $lastColumn = 0;

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        $this->lastRow = max($this->lastRow, $row);
        $this->lastColumn = max($this->lastColumn, Coordinate::columnIndexFromString($columnAddress));

        return false;
    }

    public function lastRow(): int
    {
        return $this->lastRow;
    }

    public function lastColumn(): int
    {
        return $this->lastColumn;
    }
}
