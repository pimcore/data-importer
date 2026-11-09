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

use Pimcore\Model\Tool\TmpStore;

/**
 * Keeps a run in the temporary store, as the Data Importer keeps a run's progress. One entry per failure, so a run
 * with many of them does not write ever more with each row.
 *
 * @internal
 */
final class TmpStoreRunStore implements RunStoreInterface
{
    private const string RUN_PREFIX = 'data_importer_ad_hoc_run_';

    private const string FAILURE_PREFIX = 'data_importer_ad_hoc_run_failure_';

    private const string RECORDED_PREFIX = 'data_importer_ad_hoc_run_recorded_';

    // the states a maintenance task looks for runs in
    private const array STATE_TAGS = [
        RunRecord::MERGING => 'data_importer_ad_hoc_run_merging',
        RunRecord::DECISION => 'data_importer_ad_hoc_run_decision',
    ];

    // a run waiting for a decision is discarded after a week; kept a day longer, so that the discard finds it
    private const int DECISION_LIFETIME = 8 * 86400;

    public function find(int $userId, string $fileId): ?RunRecord
    {
        $data = TmpStore::get($this->runKey($userId, $fileId))?->getData();

        return is_array($data) ? RunRecord::fromArray($data) : null;
    }

    public function save(RunRecord $run): void
    {
        TmpStore::set(
            $this->runKey($run->userId, $run->fileId),
            $run->toArray(),
            self::STATE_TAGS[$run->state] ?? null,
            $run->isWaitingForDecision() ? self::DECISION_LIFETIME : null
        );
    }

    public function delete(int $userId, string $fileId): void
    {
        TmpStore::delete($this->runKey($userId, $fileId));
    }

    public function addFailure(RunRecord $run, RowFailure $failure): void
    {
        $tag = $this->failureTag($run);
        TmpStore::set($tag . '_' . $failure->row, $failure->toArray(), $tag);
    }

    public function takeFailures(RunRecord $run): array
    {
        $failures = [];
        foreach (TmpStore::getIdsByTag($this->failureTag($run)) as $id) {
            $data = TmpStore::get($id)?->getData();
            if (is_array($data)) {
                $failures[] = RowFailure::fromArray($data);
            }
            TmpStore::delete($id);
        }
        usort($failures, static fn (RowFailure $a, RowFailure $b): int => $a->row <=> $b->row);

        return $failures;
    }

    public function addRecorded(RunRecord $run, RecordedRow $row): void
    {
        $tag = $this->recordedTag($run);
        TmpStore::set($tag . '_' . $row->row, $row->toArray(), $tag, self::DECISION_LIFETIME);
    }

    public function recorded(RunRecord $run): array
    {
        $rows = [];
        foreach (TmpStore::getIdsByTag($this->recordedTag($run)) as $id) {
            $data = TmpStore::get($id)?->getData();
            if (is_array($data)) {
                $rows[] = RecordedRow::fromArray($data);
            }
        }
        usort($rows, static fn (RecordedRow $a, RecordedRow $b): int => $a->row <=> $b->row);

        return $rows;
    }

    public function removeRecorded(RunRecord $run, array $rows): void
    {
        foreach ($rows as $row) {
            TmpStore::delete($this->recordedTag($run) . '_' . $row);
        }
    }

    public function forgetRecorded(RunRecord $run): void
    {
        foreach (TmpStore::getIdsByTag($this->recordedTag($run)) as $id) {
            TmpStore::delete($id);
        }
    }

    public function inState(string $state): array
    {
        $runs = [];
        foreach (isset(self::STATE_TAGS[$state]) ? TmpStore::getIdsByTag(self::STATE_TAGS[$state]) : [] as $id) {
            $data = TmpStore::get($id)?->getData();
            if (is_array($data)) {
                $runs[] = RunRecord::fromArray($data);
            }
        }

        return $runs;
    }

    private function recordedTag(RunRecord $run): string
    {
        return self::RECORDED_PREFIX . $run->userId . '_' . $run->fileId;
    }

    private function runKey(int $userId, string $fileId): string
    {
        return self::RUN_PREFIX . $userId . '_' . $fileId;
    }

    private function failureTag(RunRecord $run): string
    {
        return self::FAILURE_PREFIX . $run->userId . '_' . $run->fileId;
    }
}
