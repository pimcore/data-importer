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

namespace Pimcore\Bundle\DataImporterBundle\Processing;

use Doctrine\DBAL\Exception;
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
final readonly class ImportStopper
{
    // not 'datahub_dataimporter_stopped_': config names may contain '_' and would collide with the progress entry
    private const string STOPPED_ENTRY_ID_PREFIX = 'datahub_dataimporter-stopped_';

    public function __construct(
        private QueueService $queueService,
        private DeltaChecker $deltaChecker,
    ) {
    }

    /**
     * Discards the rest of the import except the given item, which the caller marks processed afterwards. The stop is
     * recorded first and every step can be repeated, so the next attempt of that item completes a stop that failed.
     *
     * @return int the number of discarded queue items
     *
     * @throws ImportStopFailedException when the stop cannot be recorded
     * @throws Exception
     */
    public function stop(string $configName, string $jobType, int $itemId): int
    {
        if (!TmpStore::set(self::STOPPED_ENTRY_ID_PREFIX . $configName, true)) {
            throw new ImportStopFailedException(sprintf('Could not record that import `%s` stopped.', $configName));
        }

        if ($jobType === ImportProcessingService::JOB_TYPE_PROCESS
            || $this->queueService->getQueueItemCount($configName, ImportProcessingService::JOB_TYPE_PROCESS) > 0
        ) {
            // these records passed the delta check when they were queued, the next run must not skip them
            $this->deltaChecker->cleanup($configName);
        }

        TmpStore::delete(ImportProcessingService::INFO_ENTRY_ID_PREFIX . $configName);

        return $this->queueService->cleanupQueueItems($configName, $itemId);
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
