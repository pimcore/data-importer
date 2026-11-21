<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Restricts spreadsheet reading to the given row numbers so previews do not
 * need to load the whole workbook into memory.
 *
 * @internal
 */
final class PreviewRowsReadFilter implements IReadFilter
{
    /**
     * @var array<int, true>
     */
    private array $rows;

    /**
     * @param int[] $rows 1-based row numbers to read
     */
    public function __construct(array $rows)
    {
        $this->rows = array_fill_keys($rows, true);
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return isset($this->rows[$row]);
    }
}
