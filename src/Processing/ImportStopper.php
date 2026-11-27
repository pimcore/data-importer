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

namespace Pimcore\Bundle\DataImporterBundle\Processing;

use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\Exception\ImportStopFailedException;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Model\Tool\TmpStore;

/**
 * Stops an import after a failing queue item (Stop Import on Error) and keeps track of the stop until the next import
 * of the configuration starts.
 *
 * @internal
 */
final class ImportStopper
{
    // not 'datahub_dataimporter_stopped_': config names may contain '_' and would collide with the progress entry
    private const string STOPPED_ENTRY_ID_PREFIX = 'datahub_dataimporter-stopped_';

    public function __construct(
        private readonly QueueService $queueService,
        private readonly DeltaChecker $deltaChecker,
    ) {
    }

    /**
     * Discards the rest of the import except the failing item, which the caller marks processed afterwards. If this
     * fails, the failing item is still queued and its next attempt stops the import again.
     *
     * @return int the number of discarded queue items
     *
     * @throws ImportStopFailedException when the stop cannot be recorded
     */
    public function stop(string $configName, string $failedJobType, int $failedItemId): int
    {
        if ($failedJobType === ImportProcessingService::JOB_TYPE_PROCESS
            || $this->queueService->getQueueItemCount($configName, ImportProcessingService::JOB_TYPE_PROCESS) > 0
        ) {
            // these records passed the delta check when they were queued, the next run must not skip them
            $this->deltaChecker->cleanup($configName);
        }

        // the failing item itself is still queued
        if ($this->queueService->getQueueItemCount($configName) <= 1) {
            return 0;
        }

        // recorded before the queue is cleared, so that a retry of the failing item can still record it
        if (!TmpStore::set(self::STOPPED_ENTRY_ID_PREFIX . $configName, true)) {
            throw new ImportStopFailedException(sprintf('Could not record that import `%s` stopped.', $configName));
        }
        TmpStore::delete(ImportProcessingService::INFO_ENTRY_ID_PREFIX . $configName);

        return $this->queueService->cleanupQueueItems($configName, $failedItemId);
    }

    public function isStopped(string $configName): bool
    {
        return TmpStore::get(self::STOPPED_ENTRY_ID_PREFIX . $configName) !== null;
    }

    public function forgetStop(string $configName): void
    {
        TmpStore::delete(self::STOPPED_ENTRY_ID_PREFIX . $configName);
    }
}
