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

namespace Pimcore\Bundle\DataImporterBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched for every row an interpreter extracted from the source file, right before the
 * row is added to the processing queue. Listeners can modify the row, skip it, or fan it
 * out into multiple rows (each queued and imported as its own element):
 *
 * - modify:  $event->setRows([$changedRow]);
 * - skip:    $event->skipRow(); or $event->setRows([]);
 * - fan-out: $event->setRows([$rowA, $rowB, $rowC]);
 *
 * When an import uses an active cleanup strategy, every element whose identifier is not seen
 * during interpretation gets deleted or unpublished. Skipping a row therefore makes the
 * cleanup treat the row's existing element as removed from the source. Use
 * $event->skipRow(keepInCleanupIdentifierCache: true) to skip the row but still register its
 * identifier, so the existing element is left untouched.
 *
 * The same event is dispatched (with isPreview() returning true) when the Studio preview
 * renders the source columns, so columns added by listeners are visible and mappable in the
 * configuration UI. Rows skipped in preview mode are still displayed unmodified.
 */
final class PreQueueRowEvent extends Event
{
    /**
     * @var array<int, array>
     */
    private array $rows;

    private bool $keepSkippedRowInIdentifierCache = false;

    public function __construct(
        private readonly string $configName,
        private readonly string $executionType,
        private readonly array $originalRow,
        private readonly bool $preview = false,
    ) {
        $this->rows = [$originalRow];
    }

    public function getConfigName(): string
    {
        return $this->configName;
    }

    public function getExecutionType(): string
    {
        return $this->executionType;
    }

    /**
     * The row as the interpreter extracted it, unaffected by any listener.
     */
    public function getOriginalRow(): array
    {
        return $this->originalRow;
    }

    /**
     * The rows that will be queued. Initially exactly the original row.
     *
     * @return array<int, array>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Replace the rows to queue: one row to modify, an empty array to skip,
     * multiple rows to fan the source row out into multiple elements.
     *
     * @param array<int, array> $rows
     */
    public function setRows(array $rows): self
    {
        $this->rows = array_values($rows);

        return $this;
    }

    /**
     * Skip this row entirely. With $keepInCleanupIdentifierCache set to true the original
     * row's identifier is still registered, so an active cleanup strategy does not treat the
     * row's existing element as removed from the source.
     */
    public function skipRow(bool $keepInCleanupIdentifierCache = false): self
    {
        $this->rows = [];
        $this->keepSkippedRowInIdentifierCache = $keepInCleanupIdentifierCache;

        return $this;
    }

    public function isRowSkipped(): bool
    {
        return $this->rows === [];
    }

    public function shouldKeepSkippedRowInIdentifierCache(): bool
    {
        return $this->keepSkippedRowInIdentifierCache;
    }

    /**
     * True when the row is being read for the Studio preview instead of an actual import.
     */
    public function isPreview(): bool
    {
        return $this->preview;
    }
}
