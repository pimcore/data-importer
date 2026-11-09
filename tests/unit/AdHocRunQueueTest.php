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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DataImporterRunQueue;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\Event\PostPreparationEvent;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A run's items in the real queue table: the queue service cannot be replaced, and Messenger installs start on the
 * event.
 */
class AdHocRunQueueTest extends Unit
{
    private const string SEQUENTIAL = ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL;

    private QueueService $queue;

    protected function _before(): void
    {
        $this->queue = new QueueService();
    }

    protected function _after(): void
    {
        foreach ($this->queue->getAllQueueEntryIds(self::SEQUENTIAL) as $id) {
            $this->queue->markQueueEntryAsProcessed((int) $id);
        }
    }

    public function testEachRowIsQueuedForTheRunsUserAndThenTheWorkersAreStartedOnce(): void
    {
        $events = new EventDispatcher();
        $seen = [];
        $events->addListener(PostPreparationEvent::class, function (PostPreparationEvent $event) use (&$seen): void {
            $seen[] = [
                $event->getConfigName(),
                $event->getExecutionType(),
                $event->isFileInterpreted(),
                count($this->queue->getAllQueueEntryIds(self::SEQUENTIAL)),
            ];
        });
        $run = AdHocRunFixtures::run();

        (new DataImporterRunQueue($this->queue, $events))->enqueue($run, [0 => [], 2 => [1, 3]]);

        $this->assertSame([['', self::SEQUENTIAL, true, 2]], $seen, 'one start, after the last row is queued');

        $entries = array_map(
            fn (int|string $id): array => $this->queue->getQueueEntryById((int) $id),
            $this->queue->getAllQueueEntryIds(self::SEQUENTIAL)
        );
        $name = RunRecord::queueName($run->fileId);
        $jobType = ImportProcessingService::JOB_TYPE_AD_HOC_ROW;
        $this->assertSame(
            [
                [$name, $jobType, '{"row":0,"leftOut":[]}'],
                [$name, $jobType, '{"row":2,"leftOut":[1,3]}'],
            ],
            array_map(
                static fn (array $entry): array => [$entry['configName'], $entry['jobType'], $entry['data']],
                $entries
            )
        );
        $this->assertSame(
            [AdHocRunFixtures::USER_ID, AdHocRunFixtures::USER_ID],
            array_map(static fn (array $entry): int => (int) $entry['userOwner'], $entries)
        );
    }
}
