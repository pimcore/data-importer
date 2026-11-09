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
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\BranchMergeInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowImporter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWrite;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunNotifier;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunProcessor;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Notification\Schema\SendNotificationParameters;
use Pimcore\Bundle\StudioBackendBundle\Notification\Service\SendNotificationServiceInterface;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class AdHocRunProcessorTest extends Unit
{
    private const string FILE_ID = '123e4567-e89b-12d3-a456-426614174000';

    private const string FOLDER_PATH = '/Products/';

    private const string CLOSE_FAILURE = 'merge refused';

    private string $storage;

    private FileStore $files;

    private RunStoreInterface $runs;

    /** @var list<SendNotificationParameters> */
    private array $notifications = [];

    protected function _before(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-run-', true);
        mkdir($this->storage);
        $this->files = AdHocCsvFiles::store($this->storage);
        $this->runs = AdHocRunFixtures::memoryStore();
        $this->notifications = [];
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

    private function storedFile(): string
    {
        $source = tempnam(sys_get_temp_dir(), 'ad-hoc-run');
        file_put_contents($source, "sku;name\nSKU-1;Chair\n");
        $fileId = $this->files->store(AdHocRunFixtures::USER_ID, FileType::Csv, $source);
        unlink($source);

        return $fileId;
    }

    private function processor(
        ImportPlan|\Throwable $plan,
        ?User $user = null,
        int $planBuilds = 1,
        ?UserResolverInterface $users = null,
        ?RowWriterInterface $writer = null,
        ?BranchMergeInterface $branchMerge = null
    ): RunProcessor {
        $dryRun = $this->createMock(AdHocImportDryRunServiceInterface::class);
        $expect = $dryRun->expects($this->exactly($planBuilds))->method('planForRun');
        $plan instanceof \Throwable ? $expect->willThrowException($plan) : $expect->willReturn($plan);

        if ($users === null) {
            $users = $this->createStub(UserResolverInterface::class);
            $users->method('getById')->willReturn($user);
        }

        $notifications = $this->createStub(SendNotificationServiceInterface::class);
        $notifications->method('sendNotification')->willReturnCallback(
            function (SendNotificationParameters $parameters, ?object $sender): void {
                $this->assertNull($sender, 'a user sent themselves nothing, and Studio skips that');
                $this->notifications[] = $parameters;
            }
        );

        $events = new EventDispatcher();
        $writer ??= new DirectRowWriter($events, new NullLogger());

        return new RunProcessor(
            $this->runs,
            $dryRun,
            $this->files,
            new RowImporter(
                $writer,
                new MappingApplicationScope(),
                AdHocRunFixtures::dryRunMessages(),
                AdHocRunFixtures::runMessages(),
                $events,
                new NullLogger()
            ),
            $writer,
            AdHocRunFixtures::runMessages(),
            new RunNotifier($notifications, AdHocRunFixtures::runMessages(), new NullLogger()),
            $users,
            new NullLogger(),
            $branchMerge
        );
    }

    /**
     * @param list<string> $calls what it was handed, per call
     */
    private function branchMerge(array &$calls, ?\Throwable $failure = null): BranchMergeInterface
    {
        return new class($calls, $failure) implements BranchMergeInterface {
            /** @param list<string> $calls */
            public function __construct(private array &$calls, private ?\Throwable $failure)
            {
            }

            public function rowsWritten(RunRecord $run, ?ImportPlan $plan, ?User $user, array $failures): void
            {
                $this->calls[] = sprintf(
                    '%d updated, %d failed, rows %s, columns %s',
                    $run->updated,
                    $run->failed,
                    implode(',', array_map(static fn (RowFailure $failure): int => $failure->row, $failures)),
                    implode(',', $plan?->columns ?? [])
                );
                if ($this->failure !== null) {
                    throw $this->failure;
                }
            }

            public function follow(int $userId, string $fileId): void
            {
            }

            public function mergeLeavingOut(RunRecord $run, User $user): RunRecord
            {
                return $run;
            }

            public function discard(RunRecord $run, User $user): void
            {
                $this->calls[] = 'discard ' . $run->branch;
            }
        };
    }

    /**
     * @param list<RowFailure>|\Throwable $closes what the end of the run finds
     */
    private function closingWriter(array|\Throwable $closes): RowWriterInterface
    {
        return new class($closes) implements RowWriterInterface {
            /** @var list<int> the rows written when it was closed */
            public array $closed = [];

            private int $written = 0;

            /** @param list<RowFailure>|\Throwable $closes */
            public function __construct(private array|\Throwable $closes)
            {
            }

            public function open(RunRecord $run, User $user): RunRecord
            {
                // a run opens its writer before the worker
                return $run;
            }

            public function write(RowWrite $write): void
            {
                $this->written++;
            }

            public function close(RunRecord $run): array
            {
                $this->closed[] = $this->written;
                if ($this->closes instanceof \Throwable) {
                    throw $this->closes;
                }

                return $this->closes;
            }
        };
    }

    private function saved(): Concrete
    {
        $object = $this->createStub(Concrete::class);
        $object->method('isAllowed')->willReturn(true);
        $object->method('setUserModification')->willReturnSelf();
        $object->method('getRealPath')->willReturn(self::FOLDER_PATH);

        return $object;
    }

    private function refused(): Concrete
    {
        $object = $this->createStub(Concrete::class);
        $object->method('isAllowed')->willReturnCallback(static fn (string $type): bool => $type === 'view');
        $object->method('getRealPath')->willReturn(self::FOLDER_PATH);

        return $object;
    }

    private function item(int $row, array $leftOut = []): string
    {
        return (string) json_encode(['row' => $row, 'leftOut' => $leftOut]);
    }

    private function queue(string $fileId): string
    {
        return RunRecord::queueName($fileId);
    }

    private function storeRun(string $fileId, int $total): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $fileId, 'total' => $total, 'skipped' => 3]));
    }

    public function testRowsAreCountedAndOnlyTheLastOneEndsTheRun(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 2);
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan(
            [
                new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved()),
                new RowMatch(RowMatchOutcome::Existing, 'SKU-2', $this->saved()),
            ],
            [],
            $user,
            [['sku' => 'SKU-1'], ['sku' => 'SKU-2']]
        );
        $processor = $this->processor($plan, $user);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(1, $run->updated);
        $this->assertTrue($run->isRunning());
        $this->assertSame(
            FileType::Csv,
            $this->files->findType(AdHocRunFixtures::USER_ID, $fileId),
            'the upload stays'
        );
        $this->assertSame([], $this->notifications);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(2, $run->updated);
        $this->assertFalse($run->isRunning());
        $this->assertNotNull($run->finishedAt);
        $this->assertNull($this->files->findType(AdHocRunFixtures::USER_ID, $fileId), 'the upload is deleted');
        $this->assertCount(1, $this->notifications);
        $this->assertSame(AdHocRunFixtures::USER_ID, $this->notifications[0]->getRecipientId());
        $this->assertStringContainsString('autumn.csv', $this->notifications[0]->getTitle());
        $this->assertNull($this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId), 'nothing failed');
    }

    public function testAFailedRowIsKeptWithItsCellsForTheDownloadAndTheRunGoesOn(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 2);
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan(
            [
                new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->refused()),
                new RowMatch(RowMatchOutcome::Existing, 'SKU-2', $this->saved()),
            ],
            [],
            $user,
            [['sku' => 'SKU-1', 'name' => 'Chair'], ['sku' => 'SKU-2', 'name' => 'Lamp']]
        );
        $processor = $this->processor($plan, $user);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame([1, 1], [$run->updated, $run->failed]);
        $stored = json_decode((string) $this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId), true);
        $this->assertSame(['SKU', 'Name'], $stored['columns']);
        $this->assertSame(2, $stored['rows'][0]['row']);
        $this->assertSame('SKU-1', $stored['rows'][0]['matchValue']);
        $this->assertSame('update', $stored['rows'][0]['action']);
        $this->assertSame(['SKU-1', 'Chair'], $stored['rows'][0]['values']);
        $this->assertStringContainsString('may-not-save', $stored['rows'][0]['message']);
        $this->assertStringContainsString("\n", $this->notifications[0]->getMessage(), 'the notification lists it');
    }

    public function testValuesThePreviewLeftOutReachTheWriterPerRow(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $user = AdHocRunFixtures::user();
        $writers = [AdHocRunFixtures::writer(), AdHocRunFixtures::writer()];
        $plan = AdHocRunFixtures::plan(
            [new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved())],
            $writers,
            $user
        );

        $this->processor($plan, $user)->processRow(
            $this->queue($fileId),
            AdHocRunFixtures::USER_ID,
            $this->item(0, [0])
        );

        $this->assertSame([], $writers[0]->writes);
        $this->assertSame([0], $writers[1]->writes);
    }

    public function testAQueueItemNamingNoValuesToLeaveOutWritesThemAll(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $user = AdHocRunFixtures::user();
        $writers = [AdHocRunFixtures::writer()];
        $plan = AdHocRunFixtures::plan(
            [new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved())],
            $writers,
            $user
        );

        $this->processor($plan, $user)->processRow(
            $this->queue($fileId),
            AdHocRunFixtures::USER_ID,
            (string) json_encode(['row' => 0])
        );

        $this->assertSame([0], $writers[0]->writes);
    }

    public function testAPlanIsBuiltOncePerFileAndProcess(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 3);
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan(
            array_fill(0, 3, new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved())),
            [],
            $user,
            [[], [], []]
        );
        $processor = $this->processor($plan, $user);

        foreach ([0, 1, 2] as $row) {
            $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item($row));
        }

        $this->assertFalse($this->runs->find(AdHocRunFixtures::USER_ID, $fileId)->isRunning());
    }

    public function testARunWhoseFileIsGoneFailsEveryRowAndEnds(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 2);
        $processor = $this->processor(new \RuntimeException('file gone'), AdHocRunFixtures::user());

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(2, $run->failed);
        $this->assertFalse($run->isRunning());
        $stored = json_decode((string) $this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId), true);
        $this->assertStringContainsString('cannot-go-on', $stored['rows'][0]['message']);
        $this->assertSame([], $stored['rows'][0]['values']);
    }

    public function testARowThatBreaksTheLookupCountsAsFailedAndTheRunEnds(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan([new \RuntimeException('database gone')], [], $user);

        $this->processor($plan, $user)->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(1, $run->failed);
        $this->assertFalse($run->isRunning());
        $stored = json_decode((string) $this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId), true);
        $this->assertStringContainsString('cannot-go-on', $stored['rows'][0]['message']);
        $this->assertStringNotContainsString('database', $stored['rows'][0]['message']);
    }

    public function testAPhpErrorWhileLookingForTheObjectCountsAsFailedAndTheRunEnds(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan([new \TypeError('null returned')], [], $user);

        $this->processor($plan, $user)->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(1, $run->failed);
        $this->assertFalse($run->isRunning());
        $this->assertCount(1, $this->notifications);
    }

    public function testAPhpErrorBuildingThePlanFailsEveryRowAndTheRunEnds(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 2);
        $processor = $this->processor(new \TypeError('null returned'), AdHocRunFixtures::user());

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(2, $run->failed);
        $this->assertFalse($run->isRunning());
    }

    public function testARunEndsAndTellsTheUserEvenIfItsFilesCannotBeWrittenOrDeleted(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $user = AdHocRunFixtures::user();
        $storage = $this->createStub(FilesystemOperator::class);
        $storage->method('write')->willThrowException(new \RuntimeException('disk full'));
        $storage->method('delete')->willThrowException(new \RuntimeException('disk full'));
        $this->files = new FileStore($storage, new LockFactory(new InMemoryStore()));
        $plan = AdHocRunFixtures::plan([new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->refused())], [], $user);

        $this->processor($plan, $user)->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(1, $run->failed);
        $this->assertFalse($run->isRunning());
        $this->assertNotNull($run->finishedAt);
        $this->assertCount(1, $this->notifications, 'the failure is still told');
        $this->assertStringContainsString('may-not-save', $this->notifications[0]->getMessage());
    }

    public function testARunEndsEvenIfItsFailuresCannotBeKeptOrRead(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $user = AdHocRunFixtures::user();
        $this->runs = new class($this->runs) implements RunStoreInterface {
            public function __construct(private RunStoreInterface $runs)
            {
            }

            public function find(int $userId, string $fileId): ?RunRecord
            {
                return $this->runs->find($userId, $fileId);
            }

            public function save(RunRecord $run): void
            {
                $this->runs->save($run);
            }

            public function delete(int $userId, string $fileId): void
            {
                $this->runs->delete($userId, $fileId);
            }

            public function addFailure(RunRecord $run, RowFailure $failure): void
            {
                throw new \TypeError('cannot be kept');
            }

            public function takeFailures(RunRecord $run): array
            {
                throw new \LogicException('cannot be read');
            }

            public function addRecorded(RunRecord $run, RecordedRow $row): void
            {
                $this->runs->addRecorded($run, $row);
            }

            public function recorded(RunRecord $run): array
            {
                return $this->runs->recorded($run);
            }

            public function removeRecorded(RunRecord $run, array $rows): void
            {
                $this->runs->removeRecorded($run, $rows);
            }

            public function forgetRecorded(RunRecord $run): void
            {
                $this->runs->forgetRecorded($run);
            }

            public function inState(string $state): array
            {
                return $this->runs->inState($state);
            }
        };
        $plan = AdHocRunFixtures::plan([new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->refused())], [], $user);

        $this->processor($plan, $user)->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(1, $run->failed);
        $this->assertFalse($run->isRunning());
        $this->assertNull($this->files->findType(AdHocRunFixtures::USER_ID, $fileId), 'the upload is deleted');
        $this->assertCount(1, $this->notifications);
    }

    public function testARightTakenAwayBetweenTwoRowsRefusesTheNextRow(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 2);
        $user = AdHocRunFixtures::user();
        $object = $this->createStub(Concrete::class);
        $object->method('setUserModification')->willReturnSelf();
        $object->method('getRealPath')->willReturn(self::FOLDER_PATH);
        $object->method('isAllowed')->willReturnCallback(
            static fn (string $type, ?User $checked): bool => $type === 'view' || $checked === $user
        );
        $plan = AdHocRunFixtures::plan(
            [
                new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $object),
                new RowMatch(RowMatchOutcome::Existing, 'SKU-2', $object),
            ],
            [],
            $user,
            [['sku' => 'SKU-1'], ['sku' => 'SKU-2']]
        );
        $users = $this->createStub(UserResolverInterface::class);
        $users->method('getById')->willReturnOnConsecutiveCalls($user, AdHocRunFixtures::user());
        $processor = $this->processor($plan, users: $users);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame([1, 1], [$run->updated, $run->failed]);
        $stored = json_decode((string) $this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId), true);
        $this->assertSame(3, $stored['rows'][0]['row']);
        $this->assertStringContainsString('may-not-save', $stored['rows'][0]['message']);
    }

    public function testEveryRowReadsTheUserAndTheirRolesAnew(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $roleId = 12;
        $user = AdHocRunFixtures::user();
        $user->setRoles([$roleId]);
        RuntimeCache::set('user_' . AdHocRunFixtures::USER_ID, AdHocRunFixtures::user());
        RuntimeCache::set('user_' . $roleId, AdHocRunFixtures::user());
        $cachedWhenRead = null;
        $users = $this->createStub(UserResolverInterface::class);
        $users->method('getById')->willReturnCallback(
            static function () use ($user, &$cachedWhenRead): User {
                $cachedWhenRead = RuntimeCache::isRegistered('user_' . AdHocRunFixtures::USER_ID);

                return $user;
            }
        );
        $plan = AdHocRunFixtures::plan([new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved())], [], $user);

        $this->processor($plan, users: $users)
            ->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $this->assertFalse($cachedWhenRead, 'getById would answer with the user as a worker first saw them');
        $this->assertFalse(RuntimeCache::isRegistered('user_' . $roleId));
        $this->assertSame(1, $this->runs->find(AdHocRunFixtures::USER_ID, $fileId)->updated);
    }

    public function testARowOnlyTheEndOfTheRunFoundToHaveFailedIsListedAndCounted(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 2);
        $user = AdHocRunFixtures::user();
        $writer = $this->closingWriter([new RowFailure(3, 'SKU-2', 'update', self::CLOSE_FAILURE, ['SKU-2', 'Lamp'])]);
        $plan = AdHocRunFixtures::plan(
            [
                new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved()),
                new RowMatch(RowMatchOutcome::Existing, 'SKU-2', $this->saved()),
            ],
            [],
            $user,
            [['sku' => 'SKU-1'], ['sku' => 'SKU-2']]
        );
        $processor = $this->processor($plan, $user, writer: $writer);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $this->assertSame([], $writer->closed, 'not before the last row');

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $this->assertSame([2], $writer->closed, 'once, when every row has been written');
        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame([1, 1], [$run->updated, $run->failed]);
        $this->assertFalse($run->isRunning());
        $stored = json_decode((string) $this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId), true);
        $this->assertCount(1, $stored['rows']);
        $this->assertSame(['SKU-2', 'Lamp'], $stored['rows'][0]['values']);
        $this->assertSame(self::CLOSE_FAILURE, $stored['rows'][0]['message']);
        $this->assertStringContainsString(self::CLOSE_FAILURE, $this->notifications[0]->getMessage());
    }

    public function testARunEndsAndTellsTheUserEvenIfItsWriterCannotClose(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan([new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved())], [], $user);
        $writer = $this->closingWriter(new \RuntimeException('the merge went away'));

        $this->processor($plan, $user, writer: $writer)
            ->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame([1, 0], [$run->updated, $run->failed]);
        $this->assertFalse($run->isRunning());
        $this->assertCount(1, $this->notifications);
    }

    public function testTheRowsOfABranchRunAreHandedToTheMergeOnceTheLastIsWritten(): void
    {
        $fileId = $this->storedFile();
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $fileId, 'total' => 2, 'branch' => 'branch-1']));
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan(
            [new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved()), new \RuntimeException('lookup failed')],
            [],
            $user,
            [['sku' => 'SKU-1'], ['sku' => 'SKU-2']]
        );
        $calls = [];
        $processor = $this->processor($plan, $user, branchMerge: $this->branchMerge($calls));

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $this->assertSame([], $calls, 'not before the last row');

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $this->assertSame(['1 updated, 1 failed, rows 3, columns SKU,Name'], $calls);
        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(RunRecord::RUNNING, $run?->state, 'the merge, not the worker, ends a branch run');
        $this->assertSame(FileType::Csv, $this->files->findType(AdHocRunFixtures::USER_ID, $fileId), 'the file stays for a decision');
        $this->assertSame([], $this->notifications);
    }

    public function testABranchRunWhoseMergeCannotGoOnEndsWithNothingSaved(): void
    {
        $fileId = $this->storedFile();
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $fileId, 'total' => 1, 'branch' => 'branch-1']));
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan([new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved())], [], $user);
        $calls = [];

        $this->processor($plan, $user, branchMerge: $this->branchMerge($calls, new \RuntimeException('the database went away')))
            ->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame([RunRecord::DONE, 0, 1], [$run?->state, $run?->updated, $run?->failed]);
        $this->assertSame('data-importer.ad-hoc-import.run.cannot-go-on', $run?->notice);
    }

    public function testABranchRunWhoseMergeFailsUnexpectedlyClosesItsBranchAndKeepsItsFailures(): void
    {
        $fileId = $this->storedFile();
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $fileId, 'total' => 2, 'branch' => 'branch-1']));
        $user = AdHocRunFixtures::user();
        $plan = AdHocRunFixtures::plan(
            [new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved()), new \RuntimeException('lookup failed')],
            [],
            $user,
            [['sku' => 'SKU-1'], ['sku' => 'SKU-2']]
        );
        $calls = [];
        $processor = $this->processor($plan, $user, branchMerge: $this->branchMerge($calls, new \RuntimeException('db gone')));

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $this->assertSame(['1 updated, 1 failed, rows 3, columns SKU,Name', 'discard branch-1'], $calls);
        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame([RunRecord::DONE, 0, 2], [$run?->state, $run?->updated, $run?->failed]);
        $this->assertNull($this->files->findType(AdHocRunFixtures::USER_ID, $fileId), 'the upload is deleted');
        $stored = json_decode((string) $this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId), true);
        $this->assertSame([3], array_column($stored['rows'] ?? [], 'row'), 'the row that failed before the branch is kept');
        $this->assertCount(1, $this->notifications);
    }

    public function testABranchRunOfAUserDeletedMeanwhileWhoseMergeFailsForgetsItsRows(): void
    {
        $fileId = $this->storedFile();
        $run = AdHocRunFixtures::run(['fileId' => $fileId, 'total' => 1, 'branch' => 'branch-1']);
        $this->runs->save($run);
        $this->runs->addRecorded($run, new RecordedRow(2, 42, null, 'SKU-1', 'update', 'SKU-1', '/Products', ['SKU-1'], []));
        $calls = [];

        $merge = $this->branchMerge($calls, new \RuntimeException('db gone'));
        $this->processor(new \RuntimeException('not asked'), null, 0, branchMerge: $merge)
            ->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $this->assertCount(1, $calls, 'nobody is left to close the branch as');
        $this->assertSame(RunRecord::DONE, $this->runs->find(AdHocRunFixtures::USER_ID, $fileId)?->state);
        $this->assertSame([], $this->runs->recorded($run));
        $this->assertSame([], $this->notifications);
    }

    public function testAQueueItemOfAnEndedOrUnknownRunIsIgnored(): void
    {
        $fileId = $this->storedFile();
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $fileId])->finished(1));
        $processor = $this->processor(new \RuntimeException('not asked'), AdHocRunFixtures::user(), 0);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $processor->processRow($this->queue(self::FILE_ID), AdHocRunFixtures::USER_ID, $this->item(0));

        $this->assertSame(0, $this->runs->find(AdHocRunFixtures::USER_ID, $fileId)->updated);
    }

    public function testARowAListenerFailedAfterTheSaveCountsAsSavedNotAsFailed(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $user = AdHocRunFixtures::user();
        $events = new EventDispatcher();
        $events->addListener(PostSaveEvent::class, static function (): void {
            throw new \TypeError('search index is down');
        });
        $plan = AdHocRunFixtures::plan(
            [new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved())],
            [],
            $user,
            [['sku' => 'SKU-1']]
        );
        $writer = new DirectRowWriter($events, new NullLogger());

        $this->processor($plan, $user, writer: $writer)
            ->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame([1, 0], [$run->updated, $run->failed]);
        $this->assertNull($this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId));
    }

    public function testAnAccountDeactivatedBetweenTwoRowsSavesNothingMore(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 2);
        $active = AdHocRunFixtures::user();
        $deactivated = AdHocRunFixtures::user();
        $deactivated->setActive(false);
        $writer = $this->closingWriter([]);
        $plan = AdHocRunFixtures::plan(
            [
                new RowMatch(RowMatchOutcome::Existing, 'SKU-1', $this->saved()),
                new RowMatch(RowMatchOutcome::Existing, 'SKU-2', $this->saved()),
            ],
            [],
            $active,
            [['sku' => 'SKU-1'], ['sku' => 'SKU-2']]
        );
        $users = $this->createStub(UserResolverInterface::class);
        $users->method('getById')->willReturnOnConsecutiveCalls($active, $deactivated);
        $processor = $this->processor($plan, users: $users, writer: $writer);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(1));

        $this->assertSame([1], $writer->closed, 'an administrator too: only the first row reached the writer');
        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame([1, 1], [$run->updated, $run->failed]);
        $this->assertFalse($run->isRunning());
        $stored = json_decode((string) $this->files->readFailedRows(AdHocRunFixtures::USER_ID, $fileId), true);
        $this->assertStringContainsString('cannot-go-on', $stored['rows'][0]['message']);
    }

    public function testAUserDeletedMeanwhileFailsTheRowsWithoutANotification(): void
    {
        $fileId = $this->storedFile();
        $this->storeRun($fileId, 1);
        $processor = $this->processor(new \RuntimeException('not asked'), null, 0);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $fileId);
        $this->assertSame(1, $run->failed);
        $this->assertFalse($run->isRunning());
        $this->assertSame([], $this->notifications);
    }
}
