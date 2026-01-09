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

namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataHubSimpleRestBundle\Tests\ServiceTester;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;

class QueueTest extends Unit
{
    /**
     * @var ServiceTester
     */
    protected $tester;

    public function testQueue()
    {
        /**
         * @var QueueService $queueService
         */
        $queueService = $this->tester->grabService(QueueService::class);

        $queueService->addItemToQueue(
            'tmp',
            ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
            ImportProcessingService::JOB_TYPE_PROCESS,
            'some data'
        );
        $count = $queueService->getQueueItemCount('tmp');
        $this->assertEquals($count, 1, 'Queue Item count check');

        $entryIds = $queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL, );
        $this->assertCount(1, $entryIds, 'Queue Item count check');

        $entry = $queueService->getQueueEntryById($entryIds[0]);

        $this->assertEquals($entry['data'], 'some data', 'Queue Item id check');
        $this->assertEquals($entry['jobType'], ImportProcessingService::JOB_TYPE_PROCESS, 'Queue Item id check');

        $queueService->markQueueEntryAsProcessed($entryIds[0]);

        $count = $queueService->getQueueItemCount('tmp');
        $this->assertEquals(0, $count, 'Queue Item count re-check');
    }
}
