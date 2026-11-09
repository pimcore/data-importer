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

    public function find(int $userId, string $fileId): ?RunRecord
    {
        $data = TmpStore::get($this->runKey($userId, $fileId))?->getData();

        return is_array($data) ? RunRecord::fromArray($data) : null;
    }

    public function save(RunRecord $run): void
    {
        TmpStore::set($this->runKey($run->userId, $run->fileId), $run->toArray());
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

    private function runKey(int $userId, string $fileId): string
    {
        return self::RUN_PREFIX . $userId . '_' . $fileId;
    }

    private function failureTag(RunRecord $run): string
    {
        return self::FAILURE_PREFIX . $run->userId . '_' . $run->fileId;
    }
}
