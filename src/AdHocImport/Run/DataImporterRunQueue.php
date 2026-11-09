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

use Pimcore\Bundle\DataImporterBundle\Event\PostPreparationEvent;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A run's rows wait in the Data Importer's own queue, one item per row, and its workers take them. A run has no
 * stored configuration: its items carry a job type of their own, so the processing never looks for one.
 *
 * @internal
 */
final readonly class DataImporterRunQueue implements RunQueueInterface
{
    // rows in the file's order, one worker at a time, which keeps the run's counters right
    private const string EXECUTION_TYPE = ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL;

    public function __construct(
        private QueueService $queue,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function enqueue(RunRecord $run, array $rows): void
    {
        $queueName = RunRecord::queueName($run->fileId);

        try {
            foreach ($rows as $index => $leftOut) {
                $this->queue->addItemToQueue(
                    $queueName,
                    self::EXECUTION_TYPE,
                    ImportProcessingService::JOB_TYPE_AD_HOC_ROW,
                    (string) json_encode(['row' => $index, 'leftOut' => $leftOut]),
                    $run->userId
                );
            }

            $this->eventDispatcher->dispatch(
                new PostPreparationEvent(RowImporter::CONFIG_NAME, self::EXECUTION_TYPE, true)
            );
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 whatever stopped it, a next run must not count these rows
            $this->queue->cleanupQueueItems($queueName);

            throw $throwable;
        }
    }
}
