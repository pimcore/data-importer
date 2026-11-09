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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\CheckedFile;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\BranchMergeInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWrite;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunQueueInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunService;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class AdHocImportRunServiceTest extends Unit
{
    private string $storage;

    private FileStore $files;

    private RunStoreInterface $runs;

    private string $fileId;

    /** @var array<int, list<int>>|null */
    private ?array $queued = null;

    private LockFactory $locks;

    protected function _before(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-run-service-', true);
        mkdir($this->storage);
        $this->files = AdHocCsvFiles::store($this->storage);
        $this->runs = AdHocRunFixtures::memoryStore();
        $this->queued = null;
        $this->locks = new LockFactory(new InMemoryStore());

        $source = tempnam(sys_get_temp_dir(), 'ad-hoc-run');
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

    /**
     * @param list<DryRunRow> $results
     */
    private function service(
        array $results = [],
        int $checks = 1,
        ?RunQueueInterface $failingQueue = null,
        ?RowWriterInterface $writer = null,
        ?\Closure $duringCheck = null,
        ?BranchMergeInterface $branchMerge = null
    ): AdHocImportRunService
    {
        $dryRun = $this->createMock(AdHocImportDryRunServiceInterface::class);
        $dryRun->expects($this->exactly($checks))->method('check')->willReturnCallback(
            static function () use ($results, $duringCheck): CheckedFile {
                if ($duringCheck !== null) {
                    $duringCheck();
                }

                return new CheckedFile(AdHocRunFixtures::plan([], []), $results);
            }
        );

        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn(AdHocRunFixtures::user());

        $queue = new class($this) implements RunQueueInterface {
            public function __construct(private AdHocImportRunServiceTest $test)
            {
            }

            public function enqueue(RunRecord $run, array $rows): void
            {
                $this->test->queuedRows($rows);
            }
        };

        return new AdHocImportRunService(
            $security,
            $dryRun,
            $this->runs,
            $this->files,
            $failingQueue ?? $queue,
            $writer ?? new DirectRowWriter(new EventDispatcher(), new NullLogger()),
            AdHocRunFixtures::dryRunMessages(),
            $this->locks,
            $branchMerge
        );
    }

    /** @var list<string> what the branch merge was asked to do */
    private array $merges = [];

    private function branchMerge(): BranchMergeInterface
    {
        return new class($this->merges) implements BranchMergeInterface {
            /** @param list<string> $calls */
            public function __construct(private array &$calls)
            {
            }

            public function rowsWritten(RunRecord $run, ?ImportPlan $plan, ?User $user, array $failures): void
            {
                $this->calls[] = 'rows written';
            }

            public function follow(int $userId, string $fileId): void
            {
                $this->calls[] = 'follow';
            }

            public function mergeLeavingOut(RunRecord $run, User $user): RunRecord
            {
                $this->calls[] = 'merge leaving out ' . $run->fileId;

                return $run->merging(3, 0, 1);
            }

            public function discard(RunRecord $run, User $user): void
            {
                $this->calls[] = 'discard ' . $run->branch;
            }
        };
    }

    private function waitingRun(int $folderId = 3, int $userId = AdHocRunFixtures::USER_ID, ?string $fileId = null): RunRecord
    {
        $run = AdHocRunFixtures::run([
            'fileId' => $fileId ?? $this->fileId,
            'userId' => $userId,
            'branch' => AdHocFakeBranches::BRANCH,
            'parameters' => ['folderId' => $folderId],
        ])->waitingForDecision(1);
        $this->runs->save($run);

        return $run;
    }

    /**
     * @param array<int, list<int>> $rows
     */
    public function queuedRows(array $rows): void
    {
        $this->queued = $rows;
    }

    private function parameters(string $errorMode): AdHocImportRunParameters
    {
        return new AdHocImportRunParameters(
            new AdHocImportDryRunParameters(3, 'PROD', AdHocImportDryRunParameters::MATCH_BY_FIELD, 'sku', 'sku'),
            $errorMode,
            'autumn.csv'
        );
    }

    /**
     * @return list<DryRunRow>
     */
    private function results(): array
    {
        return [
            new DryRunRow(2, 'SKU-1', DryRunRow::UPDATE),
            new DryRunRow(3, 'SKU-2', null, 'in the file twice'),
            new DryRunRow(
                4,
                'SKU-3',
                DryRunRow::CREATE,
                invalid: [['field' => 0, 'label' => 'price', 'value' => 'x', 'message' => 'no number']],
                leftOut: [0]
            ),
            new DryRunRow(5, 'SKU-4', DryRunRow::UPDATE, invalid: [], blocked: true),
        ];
    }

    public function testAQueueThatFailsLeavesNoRunThatNeverEnds(): void
    {
        $failure = new \RuntimeException('the database went away');
        $queue = $this->createStub(RunQueueInterface::class);
        $queue->method('enqueue')->willThrowException($failure);

        try {
            $this->service($this->results(), failingQueue: $queue)->start(
                $this->fileId,
                $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
            );
            $this->fail('the user must learn that the import did not start');
        } catch (\RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }

        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId), 'it would hold the file');
    }

    public function testASecondStartWhileTheFirstIsBeingPreparedIsRefusedAndQueuesNothing(): void
    {
        $parameters = $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS);
        $service = null;
        $refused = null;
        $secondRequest = function () use (&$service, &$refused, $parameters): void {
            try {
                $service->start($this->fileId, $parameters);
            } catch (InvalidArgumentException $exception) {
                $refused = $exception;
            }
            $this->assertNull($this->queued, 'the second request queued rows while the first one was checking');
        };
        $service = $this->service($this->results(), duringCheck: $secondRequest);

        $service->start($this->fileId, $parameters);

        $this->assertInstanceOf(InvalidArgumentException::class, $refused);
        $this->assertStringContainsString('is being imported', $refused->getMessage());
        $this->assertSame([0 => [], 3 => []], $this->queued, 'the first request goes on');
    }

    public function testStartingAgainWorksOnceAStartIsOver(): void
    {
        $parameters = $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS);
        $queue = $this->createStub(RunQueueInterface::class);
        $queue->method('enqueue')->willReturnOnConsecutiveCalls(
            $this->throwException(new \RuntimeException('the database went away')),
            null
        );
        $service = $this->service($this->results(), 2, $queue);

        try {
            $service->start($this->fileId, $parameters);
            $this->fail('the first start has to fail');
        } catch (\RuntimeException) {
            // what the next attempt must not be held up by
        }

        $service->start($this->fileId, $parameters);

        $this->assertTrue($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId)?->isRunning());
    }

    public function testTheWriterIsOpenedForTheRunBeforeItsRowsAreQueued(): void
    {
        $steps = [];
        $writer = $this->createStub(RowWriterInterface::class);
        $writer->method('open')->willReturnCallback(static function (RunRecord $run) use (&$steps): RunRecord {
            $steps[] = 'open ' . $run->fileId;

            return $run;
        });
        $queue = $this->createStub(RunQueueInterface::class);
        $queue->method('enqueue')->willReturnCallback(static function (RunRecord $run) use (&$steps): void {
            $steps[] = 'queue ' . $run->fileId;
        });

        $this->service($this->results(), failingQueue: $queue, writer: $writer)->start(
            $this->fileId,
            $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
        );

        $this->assertSame(['open ' . $this->fileId, 'queue ' . $this->fileId], $steps);
    }

    public function testAStartThatFailsAfterTheBranchIsOpenClosesItsBranch(): void
    {
        $failure = new \RuntimeException('the database went away');
        $queue = $this->createStub(RunQueueInterface::class);
        $queue->method('enqueue')->willThrowException($failure);
        $writer = $this->createStub(RowWriterInterface::class);
        $writer->method('open')->willReturnCallback(
            static fn (RunRecord $run): RunRecord => $run->withBranch(AdHocFakeBranches::BRANCH)
        );

        try {
            $this->service($this->results(), failingQueue: $queue, writer: $writer, branchMerge: $this->branchMerge())
                ->start($this->fileId, $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS));
            $this->fail('the user must learn that the import did not start');
        } catch (\RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }

        $this->assertSame(['discard ' . AdHocFakeBranches::BRANCH], $this->merges, 'nothing else would close it');
        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId));
    }

    public function testAStartWhoseBranchCannotBeClosedTellsWhatStoppedTheStart(): void
    {
        $failure = new \RuntimeException('the database went away');
        $queue = $this->createStub(RunQueueInterface::class);
        $queue->method('enqueue')->willThrowException($failure);
        $writer = $this->createStub(RowWriterInterface::class);
        $writer->method('open')->willReturnCallback(
            static fn (RunRecord $run): RunRecord => $run->withBranch(AdHocFakeBranches::BRANCH)
        );
        $branchMerge = $this->createMock(BranchMergeInterface::class);
        $branchMerge->expects($this->once())->method('discard')->willThrowException(new \LogicException('gone'));

        try {
            $this->service($this->results(), failingQueue: $queue, writer: $writer, branchMerge: $branchMerge)
                ->start($this->fileId, $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS));
            $this->fail('the user must learn that the import did not start');
        } catch (\RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }

        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId));
    }

    public function testAWriterThatCannotOpenQueuesNothingAndLeavesNoRun(): void
    {
        $failure = new \RuntimeException('no branch to write to');
        $writer = $this->createStub(RowWriterInterface::class);
        $writer->method('open')->willThrowException($failure);

        try {
            $this->service($this->results(), writer: $writer)->start(
                $this->fileId,
                $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
            );
            $this->fail('the user must learn that the import did not start');
        } catch (\RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }

        $this->assertNull($this->queued);
        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId));
    }

    public function testSkippingQueuesOnlyTheRowsWithoutErrorsAndCountsTheRestAsSkipped(): void
    {
        $response = $this->service($this->results())->start(
            $this->fileId,
            $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
        );

        $this->assertSame([0 => [], 3 => []], $this->queued, 'a row blocked by a store but without errors is imported');
        $this->assertSame('running', $response->getState());
        $this->assertSame(2, $response->getTotal());
        $this->assertSame(2, $response->getSkipped());
        $this->assertSame([0, 0, 0], [$response->getUpdated(), $response->getCreated(), $response->getFailed()]);
        $this->assertSame(AdHocImportDryRunServiceInterface::SKIP_ROWS, $response->getErrorMode());
        $this->assertSame(0, $response->getProcessed());
        $this->assertSame('autumn.csv', $response->getFileName());
        $this->assertSame('/Products/New', $response->getNewObjectsFolder());
        $this->assertSame(4, $response->getNewObjectsFolderId());
        $this->assertSame('SKU', $response->getMatchColumn());
        $this->assertSame('run-user', $response->getUserName());
        $this->assertNotNull($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId));
    }

    public function testImportingWithoutInvalidValuesQueuesWhatTheyLeaveOut(): void
    {
        $this->service($this->results())->start(
            $this->fileId,
            $this->parameters(AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES)
        );

        $this->assertSame(
            [0 => [], 2 => [0]],
            $this->queued,
            'the blocked row is out, the invalid value goes unwritten'
        );
    }

    public function testAFileWithNoRowToImportIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service([new DryRunRow(2, 'SKU-1', null, 'no match')])->start(
            $this->fileId,
            $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
        );
    }

    public function testAFileGoneBeforeItsRowsAreQueuedIsNotFound(): void
    {
        $this->files->delete(AdHocRunFixtures::USER_ID, $this->fileId, FileType::Csv);

        $this->expectException(NotFoundHttpException::class);

        $this->service($this->results())->start(
            $this->fileId,
            $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
        );
    }

    public function testAFileBeingImportedIsNotImportedTwice(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $this->fileId]));

        $this->expectException(InvalidArgumentException::class);

        $this->service($this->results(), 0)->start(
            $this->fileId,
            $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
        );
    }

    public function testAnEndedRunCanBeImportedAgain(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $this->fileId])->finished(1));

        $response = $this->service($this->results())->start(
            $this->fileId,
            $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
        );

        $this->assertSame('running', $response->getState());
    }

    public function testTheStateOfARunNamesItsFailuresWithoutTheirCells(): void
    {
        $this->runs->save(
            AdHocRunFixtures::run(['fileId' => $this->fileId, 'failed' => 1, 'updated' => 1, 'notice' => 'Not merged'])
                ->finished(9)
        );
        $this->files->storeFailedRows(AdHocRunFixtures::USER_ID, $this->fileId, (string) json_encode([
            'columns' => ['SKU'],
            'rows' => [
                ['row' => 2, 'matchValue' => 'SKU-1', 'action' => 'update', 'message' => 'no', 'values' => ['SKU-1']],
            ],
        ]));

        $response = $this->service(checks: 0)->status($this->fileId);

        $this->assertSame('done', $response->getState());
        $this->assertSame(2, $response->getProcessed());
        $this->assertSame(9, $response->getFinishedAt());
        $this->assertSame('Not merged', $response->getNotice(), 'what belongs to no row is told above the table');
        $this->assertSame(
            [['row' => 2, 'matchValue' => 'SKU-1', 'action' => 'update', 'message' => 'no']],
            $response->getFailures()
        );
    }

    public function testAnotherUsersOrAnUnknownRunIsNotFound(): void
    {
        $this->runs->save(
            AdHocRunFixtures::run(['fileId' => $this->fileId, 'userId' => AdHocRunFixtures::USER_ID + 1])
        );

        $this->expectException(NotFoundHttpException::class);

        $this->service(checks: 0)->status($this->fileId);
    }

    public function testTheFailedRowsDownloadAsACsvThatASpreadsheetCannotExecute(): void
    {
        $this->files->storeFailedRows(AdHocRunFixtures::USER_ID, $this->fileId, (string) json_encode([
            'columns' => ['SKU', '=Name'],
            'rows' => [[
                'row' => 41,
                'matchValue' => '=cmd',
                'action' => 'create',
                'message' => '=why',
                'values' => ['=cmd', 'Chair'],
            ]],
        ]));

        $csv = $this->service(checks: 0)->failedRows($this->fileId);
        $lines = array_map(
            static fn (string $line): array => str_getcsv($line, escape: ''),
            array_values(array_filter(explode("\n", $csv)))
        );

        $this->assertSame(
            [
                'data-importer.ad-hoc-import.check.column.row',
                'SKU',
                "'=Name",
                'data-importer.ad-hoc-import.check.column.error',
            ],
            $lines[0]
        );
        $this->assertSame(['41', "'=cmd", 'Chair', "'=why"], $lines[1]);
    }

    public function testARunWithoutFailedRowsHasNoDownload(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->service(checks: 0)->failedRows($this->fileId);
    }

    public function testDiscardingForgetsAFinishedRunAndItsDownloads(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $this->fileId])->finished(1));
        $this->files->storeFailedRows(AdHocRunFixtures::USER_ID, $this->fileId, '{}');
        $this->files->storeSkippedRows(AdHocRunFixtures::USER_ID, $this->fileId, '{}');

        $this->service(checks: 0)->discard($this->fileId);

        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId));
        $this->assertNull($this->files->readFailedRows(AdHocRunFixtures::USER_ID, $this->fileId));
        $this->assertNull($this->files->readSkippedRows(AdHocRunFixtures::USER_ID, $this->fileId));
    }

    public function testARunOpensItsBranchAndKeepsItBeforeItsRowsAreQueued(): void
    {
        $writer = new class() implements RowWriterInterface {
            public function open(RunRecord $run, User $user): RunRecord
            {
                return $run->withBranch(AdHocFakeBranches::BRANCH);
            }

            public function write(RowWrite $write): void
            {
                // nothing is written here
            }

            public function close(RunRecord $run): array
            {
                return [];
            }
        };

        $this->service($this->results(), writer: $writer)->start(
            $this->fileId,
            $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS)
        );

        $run = $this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId);
        $this->assertSame(AdHocFakeBranches::BRANCH, $run?->branch, 'a worker writes into it');
        $this->assertSame('/Products', $run->targetFolder);
    }

    public function testAFileWaitingForADecisionIsNotImportedAgain(): void
    {
        $this->waitingRun();

        $this->expectException(InvalidArgumentException::class);

        $this->service(checks: 0)->start($this->fileId, $this->parameters(AdHocImportDryRunServiceInterface::SKIP_ROWS));
    }

    public function testDiscardingARunWaitingForADecisionClosesItsBranchAndDeletesItsFile(): void
    {
        $this->waitingRun();
        $this->files->storeFailedRows(AdHocRunFixtures::USER_ID, $this->fileId, '{}');

        $this->service(checks: 0, branchMerge: $this->branchMerge())->discard($this->fileId);

        $this->assertSame(['discard ' . AdHocFakeBranches::BRANCH], $this->merges);
        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId));
        $this->assertNull($this->files->findType(AdHocRunFixtures::USER_ID, $this->fileId), 'nothing is left to import');
        $this->assertNull($this->files->readFailedRows(AdHocRunFixtures::USER_ID, $this->fileId));
    }

    public function testGoingBackToTheMappingClosesTheBranchAndKeepsTheFile(): void
    {
        $this->waitingRun();
        $this->files->storeFailedRows(AdHocRunFixtures::USER_ID, $this->fileId, '{}');

        $this->service(checks: 0, branchMerge: $this->branchMerge())->withdraw($this->fileId);

        $this->assertSame(['discard ' . AdHocFakeBranches::BRANCH], $this->merges, 'the next run opens a branch of its own');
        $this->assertNull($this->runs->find(AdHocRunFixtures::USER_ID, $this->fileId));
        $this->assertSame(FileType::Csv, $this->files->findType(AdHocRunFixtures::USER_ID, $this->fileId));
        $this->assertNull($this->files->readFailedRows(AdHocRunFixtures::USER_ID, $this->fileId));
    }

    public function testMergingWithoutTheFailedRowsAnswersWithTheMergingRun(): void
    {
        $this->waitingRun();

        $answer = $this->service(checks: 0, branchMerge: $this->branchMerge())->mergeLeavingOut($this->fileId);

        $this->assertSame(['merge leaving out ' . $this->fileId], $this->merges);
        $this->assertSame(['merging', true, 3, 0], [
            $answer->getState(),
            $answer->isThroughBranch(),
            $answer->getMergeTotal(),
            $answer->getMergeDone(),
        ]);
    }

    public function testOnlyARunWaitingForADecisionIsDecidedOn(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $this->fileId])->finished(1));
        $service = $this->service(checks: 0, branchMerge: $this->branchMerge());

        foreach ([$service->mergeLeavingOut(...), $service->withdraw(...)] as $decide) {
            try {
                $decide($this->fileId);
                $this->fail('a finished run has nothing to decide');
            } catch (InvalidArgumentException) {
                $this->assertSame([], $this->merges);
            }
        }
    }

    public function testTheRunsWaitingInAFolderAreTheUsersOnly(): void
    {
        $this->waitingRun();
        $this->waitingRun(4, AdHocRunFixtures::USER_ID, 'other-folder');
        $this->waitingRun(3, 8, 'other-user');
        $this->runs->save(AdHocRunFixtures::run(['fileId' => 'running']));

        $this->assertSame(
            [['fileId' => $this->fileId, 'fileName' => 'autumn.csv']],
            $this->service(checks: 0)->waitingRuns(3)
        );
    }

    public function testARunStillGoingCannotBeDiscarded(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $this->fileId]));

        $this->expectException(InvalidArgumentException::class);

        $this->service(checks: 0)->discard($this->fileId);
    }
}
