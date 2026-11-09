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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\TmpStoreRunStore;

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
}
