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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\BranchMerge;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\MergeFollowMessage;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunNotifier;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Notification\Schema\SendNotificationParameters;
use Pimcore\Bundle\StudioBackendBundle\Notification\Service\SendNotificationServiceInterface;
use Pimcore\Contracts\ChangeControl\ConflictPolicy;
use Pimcore\Contracts\ChangeControl\Exception\BranchNotFoundException;
use Pimcore\Contracts\ChangeControl\Exception\BranchNotWritableException;
use Pimcore\Contracts\ChangeControl\MergeMode;
use Pimcore\Contracts\ChangeControl\MergeProblem;
use Pimcore\Contracts\ChangeControl\MergeProblemCode;
use Pimcore\Contracts\ChangeControl\MergeStatus;
use Pimcore\Contracts\ChangeControl\ObjectRef;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class AdHocBranchMergeTest extends Unit
{
    private string $storage;

    private FileStore $files;

    private RunStoreInterface $runs;

    private AdHocFakeBranches $branches;

    private LockFactory $locks;

    private string $fileId;

    /** @var list<string> the notifications' titles */
    private array $notified = [];

    /** @var list<string> the notifications' texts */
    private array $notifiedTexts = [];

    /** @var list<Envelope> */
    private array $dispatched = [];

    /** @var list<array{int, array<int|string, mixed>}> per PostSaveEvent: the object's id and the row */
    private array $saved = [];

    /** @var list<string> */
    private array $logged = [];

    protected function _before(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-branch-merge-', true);
        mkdir($this->storage);
        $this->files = AdHocCsvFiles::store($this->storage);
        $this->runs = AdHocRunFixtures::memoryStore();
        $this->branches = new AdHocFakeBranches();
        $this->locks = new LockFactory(new InMemoryStore());

        $source = tempnam(sys_get_temp_dir(), 'ad-hoc-branch');
        file_put_contents($source, "sku;name\nSKU-1;Chair\n");
        $this->fileId = $this->files->store(AdHocRunFixtures::USER_ID, FileType::Csv, $source);
        unlink($source);
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

    private function merge(bool $userExists = true): BranchMerge
    {
        $notifications = $this->createStub(SendNotificationServiceInterface::class);
        $notifications->method('sendNotification')->willReturnCallback(
            function (SendNotificationParameters $parameters): void {
                $this->notified[] = $parameters->getTitle();
                $this->notifiedTexts[] = $parameters->getMessage();
            }
        );

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message, array $stamps): Envelope {
            $envelope = new Envelope($message, $stamps);
            $this->dispatched[] = $envelope;

            return $envelope;
        });

        $events = new EventDispatcher();
        $events->addListener(PostSaveEvent::class, function (PostSaveEvent $event): void {
            $this->saved[] = [(int) $event->getDataObject()->getId(), $event->getRawData()];
        });

        $objects = $this->createStub(ConcreteObjectResolverInterface::class);
        $objects->method('getById')->willReturnCallback(static fn (int $id): AdHocTestObject => new AdHocTestObject([], $id));

        $users = $this->createStub(UserResolverInterface::class);
        $users->method('getById')->willReturn($userExists ? AdHocRunFixtures::user() : null);

        $logger = new class($this->logged) extends AbstractLogger {
            /** @param list<string> $logged */
            public function __construct(private array &$logged)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logged[] = (string) $message;
            }
        };

        $messages = AdHocRunFixtures::runMessages();

        return new BranchMerge(
            $this->runs,
            $this->files,
            new RunNotifier($notifications, $messages, $logger),
            $messages,
            $bus,
            $events,
            $objects,
            $users,
            $this->locks,
            $logger,
            $this->branches
        );
    }

    /**
     * Rows 2 and 3 update objects 42 and 43, row 4 creates a new object.
     *
     * @param array<string, mixed> $given
     */
    private function branchRun(array $given = []): RunRecord
    {
        $run = AdHocRunFixtures::run([
            'fileId' => $this->fileId,
            'total' => 3,
            'updated' => 2,
            'created' => 1,
            'branch' => AdHocFakeBranches::BRANCH,
            'targetFolder' => '/Products/Outdoor',
            'columns' => ['SKU', 'Name'],
            ...$given,
        ]);
        foreach ([[2, 42, null], [3, 43, null], [4, null, 'row-4']] as [$row, $id, $token]) {
            $this->runs->addRecorded($run, new RecordedRow(
                $row,
                $id,
                $token,
                'SKU-' . $row,
                $token === null ? 'update' : 'create',
                'SKU-' . $row,
                '/Products/Outdoor',
                ['SKU-' . $row, 'Chair'],
                ['sku' => 'SKU-' . $row]
            ));
        }
        $this->runs->save($run);

        return $run;
    }

    private function stored(): RunRecord
    {
        return $this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId) ?? throw new \LogicException('no run');
    }

    /**
     * @return array<int, string> per row, why it was not saved
     */
    private function failures(): array
    {
        $stored = json_decode((string) $this->files->readFailedRows(AdHocRunFixtures::USER_ID, $this->fileId), true);
        $failures = [];
        foreach ($stored['rows'] ?? [] as $row) {
            $failures[$row['row']] = $row['message'];
        }

        return $failures;
    }

    private function storeFailures(RowFailure ...$failures): void
    {
        $this->files->storeFailedRows(AdHocRunFixtures::USER_ID, $this->fileId, (string) json_encode([
            'columns' => ['SKU', 'Name'],
            'rows' => array_map(static fn (RowFailure $failure): array => $failure->toArray(), $failures),
        ]));
    }

    private function uploadExists(): bool
    {
        return $this->files->findType(AdHocRunFixtures::USER_ID, $this->fileId) !== null;
    }

    private static function branch(): \Symfony\Component\Uid\Uuid
    {
        return AdHocFakeBranches::branch();
    }

    public function testABranchWithEveryRowIsMergedAtOnceAsTheUserAndTheFileWins(): void
    {
        $this->branches->mergeAnswers = [MergeStatus::merged(
            self::branch(),
            [ObjectRef::of(42), ObjectRef::of(43), ObjectRef::created('row-4', 77)]
        )];

        $this->merge()->rowsWritten($this->branchRun(), null, AdHocRunFixtures::user(), []);

        $this->assertSame([[MergeMode::Save, ConflictPolicy::BranchWins, 'run-user']], $this->branches->merges, 'new objects stay unpublished, changed ones keep their state');
        $run = $this->stored();
        $this->assertSame([RunRecord::DONE, 2, 1, 0], [$run->state, $run->updated, $run->created, $run->failed]);
        $this->assertNotNull($run->finishedAt);
        $this->assertSame(
            [[42, ['sku' => 'SKU-2']], [43, ['sku' => 'SKU-3']], [77, ['sku' => 'SKU-4']]],
            $this->saved,
            'the import\'s events follow the merge, for each object written'
        );
        $this->assertSame([], $this->runs->recorded($run));
        $this->assertFalse($this->uploadExists(), 'the file goes once the import is merged');
        $this->assertSame(['data-importer.ad-hoc-import.run.notification.title {"{{file}}":"autumn.csv"}'], $this->notified);
    }

    public function testAnObjectThatAlreadyHoldsTheFilesValuesCountsAsUpdated(): void
    {
        $this->branches->mergeAnswers = [MergeStatus::merged(
            self::branch(),
            [ObjectRef::of(42), ObjectRef::created('row-4', 77)],
            [ObjectRef::of(43)]
        )];

        $this->merge()->rowsWritten($this->branchRun(), null, AdHocRunFixtures::user(), []);

        $run = $this->stored();
        $this->assertSame([2, 1, 0], [$run->updated, $run->created, $run->failed]);
        $this->assertSame([42, 77], array_column($this->saved, 0), 'nothing was saved for it');
    }

    public function testARowThatCouldNotBeWrittenHoldsTheMergeForADecision(): void
    {
        $failure = new RowFailure(5, 'SKU-5', 'update', 'You may not save objects in /Products/Archive', ['SKU-5', 'Chair']);

        $this->merge()->rowsWritten($this->branchRun(['total' => 4, 'failed' => 1]), null, AdHocRunFixtures::user(), [$failure]);

        $this->assertSame([], $this->branches->calls, 'nothing is merged, and the branch stays');
        $run = $this->stored();
        $this->assertSame(RunRecord::DECISION, $run->state);
        $this->assertNotNull($run->waitingSince);
        $this->assertSame([5 => 'You may not save objects in /Products/Archive'], $this->failures());
        $this->assertTrue($this->uploadExists(), 'the file stays until the user decided');
        $this->assertSame(['data-importer.ad-hoc-import.merge.decision.title {"{{file}}":"autumn.csv"}'], $this->notified);
        $this->assertStringContainsString('"{{ready}}":"3","{{failed}}":"1","{{folder}}":"/Products/Outdoor"', $this->notifiedTexts[0]);
    }

    public function testARefusedMergeNamesTheRowOfEachProblemInTheImportsOwnWords(): void
    {
        $this->branches->mergeAnswers = [MergeStatus::refused(self::branch(), 3, [
            new MergeProblem(ObjectRef::of(42), null, 'User 7 may not publish object 42', code: MergeProblemCode::NotAllowed),
            new MergeProblem(ObjectRef::token('row-4'), null, 'key taken', code: MergeProblemCode::InvalidPlace),
            new MergeProblem(null, null, 'SQLSTATE[23000]: duplicate entry in objects', code: MergeProblemCode::Other),
        ])];

        $this->merge()->rowsWritten($this->branchRun(), null, AdHocRunFixtures::user(), []);

        $run = $this->stored();
        $this->assertSame([RunRecord::DECISION, 1, 0, 2], [$run->state, $run->updated, $run->created, $run->failed]);
        $this->assertSame([
            2 => 'data-importer.ad-hoc-import.run.may-not-save {"{{folder}}":"/Products/Outdoor"}',
            4 => 'data-importer.ad-hoc-import.check.key-taken {"{{key}}":"SKU-4","{{folder}}":"/Products/Outdoor"}',
        ], $this->failures());
        $this->assertSame(
            'data-importer.ad-hoc-import.merge.notice {"{{reason}}":"data-importer.ad-hoc-import.run.save-failed-unknown"}',
            $run->notice,
            'a problem of no row is told above the table'
        );
        $this->assertStringNotContainsString('SQLSTATE', (string) json_encode([$run->toArray(), $this->failures(), $this->notifiedTexts]));
        $this->assertStringContainsString('SQLSTATE', implode("\n", $this->logged), 'the reason goes to the log');
        $this->assertSame(['merge'], $this->branches->calls, 'the branch stays open for the decision');
    }

    public function testMergingWithoutTheRowsThatCannotBeSavedTakesOnlyTheirObjectsOut(): void
    {
        $run = $this->branchRun(['state' => RunRecord::DECISION, 'updated' => 1, 'failed' => 2, 'total' => 4]);
        $this->storeFailures(
            new RowFailure(2, 'SKU-2', 'update', 'may not save', ['SKU-2', 'Chair']),
            new RowFailure(9, 'SKU-9', 'update', 'listener left it out', ['SKU-9', 'Chair'])
        );
        $this->branches->mergeAnswers = [MergeStatus::merged(self::branch(), [ObjectRef::of(43), ObjectRef::created('row-4', 77)])];

        $run = $this->merge()->mergeLeavingOut($run, AdHocRunFixtures::user());

        $this->assertEquals([ObjectRef::of(42)], $this->branches->takenOut, 'row 9 never was in the branch');
        $this->assertSame(['take out 1', 'merge'], $this->branches->calls);
        $this->assertSame([RunRecord::DONE, 1, 1, 2], [$run->state, $run->updated, $run->created, $run->failed]);
        $this->assertSame([2 => 'may not save', 9 => 'listener left it out'], $this->failures(), 'the rows left out are told as not saved');
    }

    public function testASecondRefusalShowsTheTableAgainWithItsNewNumbers(): void
    {
        $run = $this->branchRun(['state' => RunRecord::DECISION, 'updated' => 1, 'failed' => 1]);
        $this->storeFailures(new RowFailure(2, 'SKU-2', 'update', 'may not save', ['SKU-2', 'Chair']));
        $this->branches->mergeAnswers = [MergeStatus::refused(self::branch(), 2, [
            new MergeProblem(ObjectRef::of(43), 'name', 'gone', code: MergeProblemCode::ObjectGone),
        ])];

        $run = $this->merge()->mergeLeavingOut($run, AdHocRunFixtures::user());

        $this->assertSame([RunRecord::DECISION, 0, 1, 2], [$run->state, $run->updated, $run->created, $run->failed]);
        $this->assertSame([2 => 'may not save', 3 => 'data-importer.ad-hoc-import.merge.object-gone'], $this->failures());
        $this->assertSame(
            ['data-importer.ad-hoc-import.merge.decision.title {"{{file}}":"autumn.csv"}'],
            $this->notified,
            'the user is asked again'
        );
    }

    public function testLeavingOutEveryRowClosesTheBranchWithoutAMerge(): void
    {
        $run = $this->branchRun(['state' => RunRecord::DECISION, 'updated' => 0, 'created' => 0, 'failed' => 3]);
        $this->storeFailures(
            new RowFailure(2, 'SKU-2', 'update', 'no', []),
            new RowFailure(3, 'SKU-3', 'update', 'no', []),
            new RowFailure(4, 'SKU-4', 'create', 'no', [])
        );

        $run = $this->merge()->mergeLeavingOut($run, AdHocRunFixtures::user());

        $this->assertSame(['take out 3', 'discard'], $this->branches->calls);
        $this->assertSame(RunRecord::DONE, $run->state);
    }

    public function testOnlyARunWaitingForADecisionCanBeMergedWithoutRows(): void
    {
        $this->expectException(\LogicException::class);

        $this->merge()->mergeLeavingOut($this->branchRun(['state' => RunRecord::MERGING]), AdHocRunFixtures::user());
    }

    public function testAPartialMergeKeepsWhatItWroteAndTellsTheRest(): void
    {
        $this->branches->mergeAnswers = [MergeStatus::partial(self::branch(), 3, [ObjectRef::of(42)], [
            new MergeProblem(ObjectRef::token('row-4'), null, 'Deadlock found when trying to get lock', code: MergeProblemCode::WriteFailed),
        ])];

        $this->merge()->rowsWritten($this->branchRun(), null, AdHocRunFixtures::user(), []);

        $run = $this->stored();
        $this->assertSame([RunRecord::DONE, 1, 0, 2], [$run->state, $run->updated, $run->created, $run->failed]);
        $this->assertSame([
            3 => 'data-importer.ad-hoc-import.merge.interrupted',
            4 => 'data-importer.ad-hoc-import.run.save-failed-unknown',
        ], $this->failures(), 'no choice is left: the written rows stay');
        $this->assertSame([42], array_column($this->saved, 0));
    }

    public function testARunningMergeIsFollowedUntilItEnds(): void
    {
        $this->branches->mergeAnswers = [MergeStatus::running(self::branch(), 3, 0)];
        $merge = $this->merge();

        $merge->rowsWritten($this->branchRun(), null, AdHocRunFixtures::user(), []);

        $run = $this->stored();
        $this->assertSame([RunRecord::MERGING, 3, 0], [$run->state, $run->mergeTotal, $run->mergeDone]);
        $this->assertCount(1, $this->dispatched);
        $this->assertEquals(new MergeFollowMessage(AdHocRunFixtures::USER_ID, $this->fileId), $this->dispatched[0]->getMessage());
        $this->assertSame(2000, $this->dispatched[0]->last(DelayStamp::class)?->getDelay());

        $this->branches->statusAnswers = [
            MergeStatus::running(self::branch(), 3, 2),
            MergeStatus::merged(self::branch(), [ObjectRef::of(42), ObjectRef::of(43), ObjectRef::created('row-4', 77)]),
        ];
        $merge->follow(AdHocRunFixtures::USER_ID, $this->fileId);
        $this->assertSame([RunRecord::MERGING, 2], [$this->stored()->state, $this->stored()->mergeDone]);
        $this->assertCount(2, $this->dispatched);

        $merge->follow(AdHocRunFixtures::USER_ID, $this->fileId);
        $this->assertSame([RunRecord::DONE, 2, 1], [$this->stored()->state, $this->stored()->updated, $this->stored()->created]);
    }

    public function testAMergeThatWritesNothingForHalfAnHourIsGivenUp(): void
    {
        $this->branchRun(['state' => RunRecord::MERGING, 'mergeTotal' => 3, 'mergeDone' => 1, 'waitingSince' => time() - 1800]);
        $this->branches->statusAnswers = [MergeStatus::running(self::branch(), 3, 1)];

        $this->merge()->follow(AdHocRunFixtures::USER_ID, $this->fileId);

        $run = $this->stored();
        $this->assertSame([RunRecord::DONE, 0, 0, 3], [$run->state, $run->updated, $run->created, $run->failed]);
        $this->assertSame('data-importer.ad-hoc-import.merge.timed-out', $run->notice);
        $this->assertSame(array_fill(2, 3, 'data-importer.ad-hoc-import.merge.timed-out'), $this->failures());
        $this->assertSame([], $this->dispatched, 'nobody looks again');
    }

    public function testAMergeThatGetsOnIsWaitedFor(): void
    {
        $this->branchRun(['state' => RunRecord::MERGING, 'mergeTotal' => 3, 'mergeDone' => 1, 'waitingSince' => time() - 1800]);
        $this->branches->statusAnswers = [MergeStatus::running(self::branch(), 3, 2)];

        $this->merge()->follow(AdHocRunFixtures::USER_ID, $this->fileId);

        $this->assertSame(RunRecord::MERGING, $this->stored()->state);
        $this->assertGreaterThan(time() - 60, (int) $this->stored()->waitingSince);
    }

    public function testFollowingARunThatIsNotMergingDoesNothing(): void
    {
        $this->branchRun(['state' => RunRecord::DONE]);

        $this->merge()->follow(AdHocRunFixtures::USER_ID, $this->fileId);
        $this->merge()->follow(AdHocRunFixtures::USER_ID, 'no-such-file');

        $this->assertSame([], $this->branches->calls);
    }

    public function testFollowingWhileAnotherWorkerFollowsDoesNothing(): void
    {
        $this->branchRun(['state' => RunRecord::MERGING]);
        $lock = $this->locks->createLock(sprintf('data_importer_ad_hoc_merge_%d_%s', AdHocRunFixtures::USER_ID, $this->fileId));
        $lock->acquire();

        $this->merge()->follow(AdHocRunFixtures::USER_ID, $this->fileId);

        $this->assertSame([], $this->branches->calls);
    }

    public function testAMergeWhoseStatusIsGoneFailsItsRows(): void
    {
        $this->branchRun(['state' => RunRecord::MERGING]);
        $this->branches->statusAnswers = [null];

        $this->merge()->follow(AdHocRunFixtures::USER_ID, $this->fileId);

        $this->assertSame([RunRecord::DONE, 3], [$this->stored()->state, $this->stored()->failed]);
        $this->assertSame(array_fill(2, 3, 'data-importer.ad-hoc-import.merge.interrupted'), $this->failures());
    }

    public function testAMergeOfAUserDeletedMeanwhileEnds(): void
    {
        $this->branchRun(['state' => RunRecord::MERGING]);

        $this->merge(false)->follow(AdHocRunFixtures::USER_ID, $this->fileId);

        $this->assertSame(RunRecord::DONE, $this->stored()->state);
        $this->assertSame([], $this->branches->calls, 'nobody is left to ask as');
    }

    public function testAMergeThatCannotStartClosesTheBranchAndFailsTheRows(): void
    {
        $this->branches->mergeAnswers = [new BranchNotWritableException('Branch is being merged.')];

        $this->merge()->rowsWritten($this->branchRun(), null, AdHocRunFixtures::user(), []);

        $this->assertSame(['merge', 'discard'], $this->branches->calls);
        $run = $this->stored();
        $this->assertSame([RunRecord::DONE, 0, 0, 3], [$run->state, $run->updated, $run->created, $run->failed]);
        $this->assertSame(array_fill(2, 3, 'data-importer.ad-hoc-import.run.save-failed-unknown'), $this->failures());
    }

    public function testABranchWithoutARowIsClosedWithoutAMerge(): void
    {
        $failure = new RowFailure(2, 'SKU-2', 'update', 'no', []);

        $this->merge()->rowsWritten(
            $this->branchRun(['updated' => 0, 'created' => 0, 'failed' => 1, 'total' => 1]),
            AdHocRunFixtures::plan([], []),
            AdHocRunFixtures::user(),
            [$failure]
        );

        $this->assertSame(['discard'], $this->branches->calls);
        $this->assertSame(RunRecord::DONE, $this->stored()->state);
        $this->assertSame(['SKU', 'Name'], $this->stored()->columns, 'the plan\'s columns head the download');
        $this->assertSame([2 => 'no'], $this->failures());
    }

    public function testDiscardingABranchThatIsGoneAlreadyForgetsTheRun(): void
    {
        $run = $this->branchRun(['state' => RunRecord::DECISION]);
        $this->branches->discardRefusal = new BranchNotFoundException('gone');

        $this->merge()->discard($run, AdHocRunFixtures::user());

        $this->assertSame([], $this->runs->recorded($run));
    }
}
