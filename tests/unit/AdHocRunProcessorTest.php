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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowImporter;
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
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
        ImportPlan|\Exception $plan,
        ?User $user = null,
        int $planBuilds = 1
    ): RunProcessor {
        $dryRun = $this->createMock(AdHocImportDryRunServiceInterface::class);
        $expect = $dryRun->expects($this->exactly($planBuilds))->method('planForRun');
        $plan instanceof \Exception ? $expect->willThrowException($plan) : $expect->willReturn($plan);

        $users = $this->createStub(UserResolverInterface::class);
        $users->method('getById')->willReturn($user);

        $notifications = $this->createStub(SendNotificationServiceInterface::class);
        $notifications->method('sendNotification')->willReturnCallback(
            function (SendNotificationParameters $parameters, ?object $sender): void {
                $this->assertNull($sender, 'a user sent themselves nothing, and Studio skips that');
                $this->notifications[] = $parameters;
            }
        );

        $events = new EventDispatcher();
        $writer = new DirectRowWriter($events);

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
        $this->assertSame(FileType::Csv, $this->files->findType(AdHocRunFixtures::USER_ID, $fileId), 'the upload stays');
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

        $this->processor($plan, $user)->processRow($this->queue($fileId), AdHocRunFixtures::USER_ID, $this->item(0, [0]));

        $this->assertSame([], $writers[0]->writes);
        $this->assertSame([0], $writers[1]->writes);
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
