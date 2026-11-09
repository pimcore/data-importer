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

/**
 * The run of a file, for its user only.
 *
 * @internal
 */
interface RunStoreInterface
{
    public function find(int $userId, string $fileId): ?RunRecord;

    public function save(RunRecord $run): void;

    public function delete(int $userId, string $fileId): void;

    public function addFailure(RunRecord $run, RowFailure $failure): void;

    /**
     * Hands over the failures of the run and forgets them.
     *
     * @return list<RowFailure> by their row
     */
    public function takeFailures(RunRecord $run): array;

    /**
     * Keeps which object a row became in the run's branch, for as long as a decision may take.
     */
    public function addRecorded(RunRecord $run, RecordedRow $row): void;

    /**
     * @return list<RecordedRow> by their row
     */
    public function recorded(RunRecord $run): array;

    /**
     * @param list<int> $rows the rows' numbers
     */
    public function removeRecorded(RunRecord $run, array $rows): void;

    public function forgetRecorded(RunRecord $run): void;

    /**
     * @param string $state RunRecord::MERGING or RunRecord::DECISION
     *
     * @return list<RunRecord> the runs of every user in that state
     */
    public function inState(string $state): array;
}
