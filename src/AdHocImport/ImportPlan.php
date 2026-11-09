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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunPlan;

/**
 * What an import of a file goes by: the rows, how they find their objects, the fields written, and what a run keeps
 * of it.
 *
 * @internal
 */
final readonly class ImportPlan
{
    /**
     * @param list<array<int|string, mixed>> $rows the file's rows in their order, without the header row
     * @param array<int|string, string> $columns label per data index
     * @param string $matchColumn how the column holding the match value is called
     * @param array{configuration: array<mixed>, mapping: array<int|string, mixed>}|null $configuration the
     *     Data Importer configuration mapped with, as it stands now
     */
    public function __construct(
        public DryRunPlan $dryRun,
        public array $rows,
        public int $firstRowNumber,
        public array $columns,
        public string $matchColumn,
        public int $newObjectFolderId,
        public ?array $configuration,
    ) {
    }

    /**
     * @return list<string> a row's cells, in the order of the columns
     */
    public function cells(int $index): array
    {
        $row = $this->rows[$index] ?? [];

        return array_map(
            static fn (int|string $column): string => is_scalar($row[$column] ?? null) ? (string) $row[$column] : '',
            array_keys($this->columns)
        );
    }
}
