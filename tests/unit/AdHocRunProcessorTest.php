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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowImporter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWrite;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunNotifier;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunProcessor;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
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
        ?RowWriterInterface $writer = null
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
        $writer ??= new DirectRowWriter($events);

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
            new NullLogger()
        );
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

            public function open(RunRecord $run): void
            {
                // a run opens its writer before the worker
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
        $object->method('getRealPath')->willReturn('/Products/');

        return $object;
    }

    private function refused(): Concrete
    {
        $object = $this->createStub(Concrete::class);
        $object->method('isAllowed')->willReturnCallback(static fn (string $type): bool => $type === 'view');
        $object->method('getRealPath')->willReturn('/Products/');

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
        $object->method('getRealPath')->willReturn('/Products/');
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
        $writer = $this->closingWriter([new RowFailure(3, 'SKU-2', 'update', 'merge refused', ['SKU-2', 'Lamp'])]);
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
        $this->assertSame('merge refused', $stored['rows'][0]['message']);
        $this->assertStringContainsString('merge refused', $this->notifications[0]->getMessage());
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

    public function testAQueueItemOfAnEndedOrUnknownRunIsIgnored(): void
    {
        $fileId = $this->storedFile();
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $fileId])->finished(1));
        $processor = $this->processor(new \RuntimeException('not asked'), AdHocRunFixtures::user(), 0);

        $processor->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0));
        $processor->processRow($this->queue(self::FILE_ID), AdHocRunFixtures::USER_ID, $this->item(0));

        $this->assertSame(0, $this->runs->find(AdHocRunFixtures::USER_ID, $fileId)->updated);
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
