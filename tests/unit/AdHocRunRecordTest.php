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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowOutcome;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;

class AdHocRunRecordTest extends Unit
{
    public function testARunCountsEveryRowByWhatBecameOfIt(): void
    {
        $run = AdHocRunFixtures::run(['total' => 3])
            ->counted(RowOutcome::saved(false, 'SKU-1'))
            ->counted(RowOutcome::saved(true, 'SKU-2'));

        $this->assertSame([1, 1, 0], [$run->updated, $run->created, $run->failed]);
        $this->assertSame(2, $run->processed());
        $this->assertFalse($run->isComplete());

        $run = $run->counted(RowOutcome::failed('SKU-3', null, 'no'));

        $this->assertSame(1, $run->failed);
        $this->assertTrue($run->isComplete(), 'a failed row counts, so a run always ends');
        $this->assertTrue($run->isRunning(), 'until it is finished');
    }

    public function testARowOnlyTheEndFoundToHaveFailedMovesFromWrittenToFailed(): void
    {
        $run = AdHocRunFixtures::run(['total' => 3, 'updated' => 2, 'created' => 1]);

        $run = $run->failedAtClose(new RowFailure(2, 'SKU-1', 'update', 'no', []));
        $this->assertSame([1, 1, 1], [$run->updated, $run->created, $run->failed]);

        $run = $run->failedAtClose(new RowFailure(4, 'SKU-3', 'create', 'no', []));
        $this->assertSame([1, 0, 2], [$run->updated, $run->created, $run->failed]);
        $this->assertSame(3, $run->processed(), 'the counts still add up to the rows of the run');
    }

    public function testAFinishedRunIsDone(): void
    {
        $run = AdHocRunFixtures::run()->finished(1800000000);

        $this->assertFalse($run->isRunning());
        $this->assertSame(RunRecord::DONE, $run->state);
        $this->assertSame(1800000000, $run->finishedAt);
    }

    public function testARunSurvivesBeingStored(): void
    {
        $run = AdHocRunFixtures::run(['updated' => 4, 'configuration' => ['configuration' => [], 'mapping' => []]]);

        $this->assertEquals($run, RunRecord::fromArray($run->toArray()));
        $this->assertSame(FileType::Csv->value, $run->toArray()['type'], 'stored as a plain value');
    }

    public function testAFailureSurvivesBeingStored(): void
    {
        $failure = new RowFailure(41, 'SKU-1', 'create', 'no', ['SKU-1', 'Chair']);

        $this->assertEquals($failure, RowFailure::fromArray($failure->toArray()));
    }

    public function testAQueueItemNamesItsFileWithinTheColumnsLength(): void
    {
        $fileId = '123e4567-e89b-12d3-a456-426614174000';
        $name = RunRecord::queueName($fileId);

        $this->assertLessThanOrEqual(80, strlen($name));
        $this->assertSame($fileId, RunRecord::fileIdOf($name));
    }

    public function testABranchRunMergesThenEndsAndCountsWhatTheMergeDidNotWrite(): void
    {
        $run = AdHocRunFixtures::run(['updated' => 1, 'created' => 1])->withBranch('branch-1');
        $this->assertTrue($run->throughBranch());
        $this->assertFalse(AdHocRunFixtures::run()->throughBranch());

        $run = $run->merging(2, 0, 100);
        $this->assertSame([RunRecord::MERGING, 2, 0, 100, 100], [$run->state, $run->mergeTotal, $run->mergeDone, $run->waitingSince, $run->checkedAt]);
        $this->assertTrue($run->isBusy());
        $this->assertFalse($run->isRunning(), 'its rows are written');

        $run = $run->merging(2, 0, 150);
        $this->assertSame([100, 150], [$run->waitingSince, $run->checkedAt], 'only progress moves the wait');
        $this->assertFalse($run->stalled(1899, 1800));
        $this->assertTrue($run->stalled(1900, 1800));

        $run = $run->merging(2, 1, 200);
        $this->assertSame(200, $run->waitingSince);

        $run = $run->failedAfterWrite('create');
        $this->assertSame([1, 0, 1], [$run->updated, $run->created, $run->failed]);

        $run = $run->finished(300);
        $this->assertNull($run->waitingSince);
        $this->assertFalse($run->stalled(9999, 1800), 'an ended run waits for nothing');
    }

    public function testARunWaitingForADecisionHoldsItsFileButIsNotBusy(): void
    {
        $run = AdHocRunFixtures::run()->waitingForDecision(500);

        $this->assertTrue($run->isWaitingForDecision());
        $this->assertFalse($run->isBusy());
        $this->assertSame(500, $run->waitingSince);
    }

    public function testRowsOfABranchNeverMergedCountAsNotSaved(): void
    {
        $run = AdHocRunFixtures::run(['total' => 4, 'updated' => 2, 'created' => 1, 'failed' => 1])->nothingSaved();

        $this->assertSame([0, 0, 4], [$run->updated, $run->created, $run->failed]);
    }

    public function testABranchRunSurvivesBeingStored(): void
    {
        $run = AdHocRunFixtures::run()
            ->withBranch('branch-1')
            ->withColumns(['SKU', 'Name'])
            ->withNotice('The import could not be merged')
            ->merging(3, 1, 100);

        $this->assertEquals($run, RunRecord::fromArray($run->toArray()));
    }

    public function testARecordedRowSurvivesBeingStoredAndFailsAsItsRow(): void
    {
        $row = new RecordedRow(4, null, 'row-4', 'SKU-4', 'create', 'SKU-4', '/Products', ['SKU-4'], ['sku' => 'SKU-4']);

        $this->assertEquals($row, RecordedRow::fromArray($row->toArray()));
        $this->assertTrue($row->isNew());
        $this->assertSame('row-4', RecordedRow::tokenOf(4));
        $this->assertEquals(new RowFailure(4, 'SKU-4', 'create', 'no', ['SKU-4']), $row->failure('no'));
    }
}
