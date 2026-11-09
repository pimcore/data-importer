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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\TmpStoreRunStore;
use Pimcore\Model\Tool\TmpStore;

/**
 * The real store, over the database's temporary store.
 */
class AdHocTmpStoreRunStoreTest extends Unit
{
    private const int OTHER_USER_ID = 8;

    private TmpStoreRunStore $store;

    private RunRecord $run;

    private RunRecord $otherUsersRun;

    protected function _before(): void
    {
        $this->store = new TmpStoreRunStore();
        $fileId = uniqid('run-store-', true);
        $this->run = AdHocRunFixtures::run(['fileId' => $fileId]);
        $this->otherUsersRun = AdHocRunFixtures::run(['fileId' => $fileId, 'userId' => self::OTHER_USER_ID]);
    }

    protected function _after(): void
    {
        $this->store->takeFailures($this->run);
        $this->store->takeFailures($this->otherUsersRun);
        $this->store->delete($this->run->userId, $this->run->fileId);
        $this->store->forgetRecorded($this->run);
        $this->store->forgetRecorded($this->otherUsersRun);
    }

    private function recordedRow(int $row): RecordedRow
    {
        return new RecordedRow($row, 40 + $row, null, 'SKU-' . $row, 'update', 'SKU-' . $row, '/Products', ['SKU-' . $row], ['sku' => 'SKU-' . $row]);
    }

    private function failure(int $row): RowFailure
    {
        return new RowFailure($row, 'SKU-' . $row, 'update', 'refused', ['SKU-' . $row, 'Chair']);
    }

    public function testARunIsKeptPerUserUntilItIsDeleted(): void
    {
        $this->store->save($this->run);

        $found = $this->store->find($this->run->userId, $this->run->fileId);

        $this->assertInstanceOf(RunRecord::class, $found);
        $this->assertSame($this->run->toArray(), $found->toArray());
        $this->assertNull($this->store->find(self::OTHER_USER_ID, $this->run->fileId), 'it is its starter\'s only');

        $this->store->delete($this->run->userId, $this->run->fileId);

        $this->assertNull($this->store->find($this->run->userId, $this->run->fileId));
    }

    public function testARunAndItsFailuresAreGoneSevenDaysAfterTheirLastSave(): void
    {
        $this->store->save($this->run);
        $this->store->addFailure($this->run, $this->failure(4));
        $sevenDaysFromNow = time() + 7 * 86400;

        $names = $this->run->userId . '_' . $this->run->fileId;
        $run = TmpStore::get('data_importer_ad_hoc_run_' . $names);
        $failure = TmpStore::get('data_importer_ad_hoc_run_failure_' . $names . '_4');

        $this->assertInstanceOf(TmpStore::class, $run);
        $this->assertInstanceOf(TmpStore::class, $failure);
        $this->assertEqualsWithDelta($sevenDaysFromNow, $run->getExpiryDate(), 60);
        $this->assertEqualsWithDelta($sevenDaysFromNow, $failure->getExpiryDate(), 60);
    }

    public function testFailuresComeBackOnceInRowOrderAndOnlyForTheirOwnRun(): void
    {
        $this->store->addFailure($this->run, $this->failure(5));
        $this->store->addFailure($this->run, $this->failure(3));
        $this->store->addFailure($this->otherUsersRun, $this->failure(9));

        $failures = $this->store->takeFailures($this->run);

        $this->assertSame(
            [$this->failure(3)->toArray(), $this->failure(5)->toArray()],
            array_map(static fn (RowFailure $failure): array => $failure->toArray(), $failures)
        );
        $this->assertSame([], $this->store->takeFailures($this->run), 'they are taken, not copied');
        $this->assertSame(
            [$this->failure(9)->toArray()],
            array_map(
                static fn (RowFailure $failure): array => $failure->toArray(),
                $this->store->takeFailures($this->otherUsersRun)
            ),
            'another user\'s run keeps its own'
        );
    }

    public function testTheRowsInARunsBranchAreKeptInRowOrderForAsLongAsADecisionMayTake(): void
    {
        $this->store->addRecorded($this->run, $this->recordedRow(5));
        $this->store->addRecorded($this->run, $this->recordedRow(3));
        $this->store->addRecorded($this->run, $this->recordedRow(4));
        $this->store->addRecorded($this->otherUsersRun, $this->recordedRow(9));

        $this->assertEquals([$this->recordedRow(3), $this->recordedRow(4), $this->recordedRow(5)], $this->store->recorded($this->run));
        $stored = TmpStore::get('data_importer_ad_hoc_run_recorded_' . $this->run->userId . '_' . $this->run->fileId . '_3');
        $this->assertInstanceOf(TmpStore::class, $stored);
        $this->assertEqualsWithDelta(time() + 8 * 86400, $stored->getExpiryDate(), 60);

        $this->store->removeRecorded($this->run, [3, 5]);
        $this->assertEquals([$this->recordedRow(4)], $this->store->recorded($this->run));

        $this->store->forgetRecorded($this->run);
        $this->assertSame([], $this->store->recorded($this->run));
        $this->assertEquals([$this->recordedRow(9)], $this->store->recorded($this->otherUsersRun), 'its own run\'s only');
    }

    public function testTheRowsInARunsBranchLiveAsLongAsTheRunWaitingForADecisionAgain(): void
    {
        $this->store->addRecorded($this->run, $this->recordedRow(3));
        $key = 'data_importer_ad_hoc_run_recorded_' . $this->run->userId . '_' . $this->run->fileId;
        TmpStore::set($key . '_3', $this->recordedRow(3)->toArray(), $key, 60);

        $this->store->save($this->run->waitingForDecision(time()));

        $this->assertEqualsWithDelta(time() + 8 * 86400, (int) TmpStore::get($key . '_3')?->getExpiryDate(), 60);
        $this->assertEquals([$this->recordedRow(3)], $this->store->recorded($this->run));
    }

    public function testRunsAreFoundByTheStateAMaintenanceTaskLooksFor(): void
    {
        $this->store->save($this->run->waitingForDecision(time()));
        $this->store->save($this->otherUsersRun->merging(3, 0, time()));

        $this->assertContains($this->run->fileId, $this->fileIdsOf($this->store->inState(RunRecord::DECISION), $this->run->userId));
        $this->assertContains($this->otherUsersRun->fileId, $this->fileIdsOf($this->store->inState(RunRecord::MERGING), self::OTHER_USER_ID));
        $this->assertSame([], $this->store->inState(RunRecord::RUNNING), 'only the states it looks for');
        $waiting = TmpStore::get('data_importer_ad_hoc_run_' . $this->run->userId . '_' . $this->run->fileId);
        $this->assertEqualsWithDelta(time() + 8 * 86400, (int) $waiting?->getExpiryDate(), 60, 'kept until the discard finds it');

        $this->store->save($this->run->waitingForDecision(time())->finished(time()));
        $this->assertNotContains($this->run->fileId, $this->fileIdsOf($this->store->inState(RunRecord::DECISION), $this->run->userId));
        $this->store->delete(self::OTHER_USER_ID, $this->otherUsersRun->fileId);
    }

    /**
     * @param list<RunRecord> $runs
     *
     * @return list<string>
     */
    private function fileIdsOf(array $runs, int $userId): array
    {
        return array_values(array_map(
            static fn (RunRecord $run): string => $run->fileId,
            array_filter($runs, static fn (RunRecord $run): bool => $run->userId === $userId)
        ));
    }
}
