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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\BranchImportsTask;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\BranchRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\MergeFollowHandler;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\ObjectChanges;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\WriteWay;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\BranchMergeInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\MergeFollowMessage;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Model\User;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AdHocBranchImportsTaskTest extends Unit
{
    private string $storage;

    private FileStore $files;

    private RunStoreInterface $runs;

    /** @var list<string> */
    private array $calls = [];

    protected function _before(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-branch-task-', true);
        mkdir($this->storage);
        $this->files = AdHocCsvFiles::store($this->storage);
        $this->runs = AdHocRunFixtures::memoryStore();
        $this->calls = [];
    }

    protected function _after(): void
    {
        foreach (glob($this->storage . '/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->storage . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            @rmdir($directory);
        }
        @rmdir($this->storage);
    }

    private function merges(?\Throwable $failure = null): BranchMergeInterface
    {
        return new class($this->calls, $failure) implements BranchMergeInterface {
            /** @param list<string> $calls */
            public function __construct(private array &$calls, private ?\Throwable $failure)
            {
            }

            public function rowsWritten(RunRecord $run, ?ImportPlan $plan, ?User $user, array $failures): void
            {
            }

            public function follow(int $userId, string $fileId): void
            {
                $this->calls[] = 'follow ' . $fileId;
                if ($this->failure !== null) {
                    throw $this->failure;
                }
            }

            public function mergeLeavingOut(RunRecord $run, User $user): RunRecord
            {
                return $run;
            }

            public function discard(RunRecord $run, User $user): void
            {
                $this->calls[] = 'discard ' . $run->fileId;
            }
        };
    }

    private function task(?BranchMergeInterface $merges = null, bool $userExists = true): BranchImportsTask
    {
        $users = $this->createStub(UserResolverInterface::class);
        $users->method('getById')->willReturn($userExists ? AdHocRunFixtures::user() : null);

        return new BranchImportsTask($this->runs, $merges ?? $this->merges(), $this->files, $users, new NullLogger());
    }

    private function storedFile(): string
    {
        $source = tempnam(sys_get_temp_dir(), 'ad-hoc-task');
        file_put_contents($source, "sku;name\nSKU-1;Chair\n");
        $fileId = $this->files->store(AdHocRunFixtures::USER_ID, FileType::Csv, $source);
        unlink($source);

        return $fileId;
    }

    public function testAMergeNoWorkerLookedAtForAMinuteIsFollowedHere(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => 'unfollowed'])->merging(3, 1, time() - 61));
        $this->runs->save(AdHocRunFixtures::run(['fileId' => 'followed'])->merging(3, 1, time() - 10));

        $this->task()->execute();

        $this->assertSame(['follow unfollowed'], $this->calls);
    }

    public function testAnImportNobodyDecidedOnWithinAWeekIsDiscarded(): void
    {
        $fileId = $this->storedFile();
        $this->files->storeFailedRows(AdHocRunFixtures::USER_ID, $fileId, '{}');
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $fileId])->waitingForDecision(time() - 7 * 86400));
        $this->runs->save(AdHocRunFixtures::run(['fileId' => 'recent'])->waitingForDecision(time() - 6 * 86400));

        $this->task()->execute();

        $this->assertSame(['discard ' . $fileId], $this->calls);
        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, $fileId));
        $this->assertNull($this->files->findType(AdHocRunFixtures::USER_ID, $fileId));
        $this->assertNull($this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId));
        $this->assertNotNull($this->runs->find(AdHocRunFixtures::USER_ID, 'recent'));
    }

    public function testAnImportOfAUserDeletedMeanwhileIsForgotten(): void
    {
        $run = AdHocRunFixtures::run(['fileId' => 'orphan'])->waitingForDecision(time() - 8 * 86400);
        $this->runs->save($run);
        $this->runs->addRecorded($run, new RecordedRow(2, 42, null, 'SKU-1', 'update', 'SKU-1', '/Products', [], []));

        $this->task(userExists: false)->execute();

        $this->assertSame([], $this->calls, 'nobody is left to close the branch as');
        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, 'orphan'));
        $this->assertSame([], $this->runs->recorded($run));
    }

    public function testOneImportThatFailsKeepsNoOtherWaiting(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => 'first'])->merging(3, 1, time() - 61));
        $this->runs->save(AdHocRunFixtures::run(['fileId' => 'second'])->merging(3, 1, time() - 61));

        $this->task($this->merges(new \RuntimeException('the database went away')))->execute();

        $this->assertSame(['follow first', 'follow second'], $this->calls);
    }

    public function testAFollowMessageAsksTheMergeOfItsRun(): void
    {
        (new MergeFollowHandler($this->merges()))(new MergeFollowMessage(AdHocRunFixtures::USER_ID, 'file-1'));

        $this->assertSame(['follow file-1'], $this->calls);
    }

    public function testRowsGoIntoABranchOnlyWhenChangeControlIsInstalled(): void
    {
        $direct = new DirectRowWriter(new EventDispatcher(), new NullLogger());
        $way = fn (?AdHocFakeBranches $branches): WriteWay => new WriteWay(
            $direct,
            new ObjectChanges(),
            $this->createStub(ConcreteObjectResolverInterface::class),
            $this->runs,
            AdHocRunFixtures::runMessages(),
            $branches
        );

        $this->assertSame($direct, $way(null)->writer());
        $this->assertInstanceOf(BranchRowWriter::class, $way(new AdHocFakeBranches())->writer());
    }
}
