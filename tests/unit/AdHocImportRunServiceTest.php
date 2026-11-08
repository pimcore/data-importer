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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunQueueInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunService;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdHocImportRunServiceTest extends Unit
{
    private string $storage;

    private FileStore $files;

    private RunStoreInterface $runs;

    private string $fileId;

    /** @var array<int, list<int>>|null */
    private ?array $queued = null;

    protected function _before(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-run-service-', true);
        mkdir($this->storage);
        $this->files = AdHocCsvFiles::store($this->storage);
        $this->runs = AdHocRunFixtures::memoryStore();
        $this->queued = null;

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
    private function service(array $results = [], int $checks = 1): AdHocImportRunService
    {
        $dryRun = $this->createMock(AdHocImportDryRunServiceInterface::class);
        $dryRun->expects($this->exactly($checks))->method('check')->willReturn(
            new CheckedFile(AdHocRunFixtures::plan([], []), $results)
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
            $queue,
            new DirectRowWriter(new EventDispatcher()),
            AdHocRunFixtures::dryRunMessages()
        );
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

        $this->assertSame([0 => [], 2 => [0]], $this->queued, 'the blocked row is out, the invalid value goes unwritten');
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
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $this->fileId, 'failed' => 1, 'updated' => 1])->finished(9));
        $this->files->storeFailedRows(AdHocRunFixtures::USER_ID, $this->fileId, (string) json_encode([
            'columns' => ['SKU'],
            'rows' => [['row' => 2, 'matchValue' => 'SKU-1', 'action' => 'update', 'message' => 'no', 'values' => ['SKU-1']]],
        ]));

        $response = $this->service(checks: 0)->status($this->fileId);

        $this->assertSame('done', $response->getState());
        $this->assertSame(2, $response->getProcessed());
        $this->assertSame(9, $response->getFinishedAt());
        $this->assertSame(
            [['row' => 2, 'matchValue' => 'SKU-1', 'action' => 'update', 'message' => 'no']],
            $response->getFailures()
        );
    }

    public function testAnotherUsersOrAnUnknownRunIsNotFound(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $this->fileId, 'userId' => AdHocRunFixtures::USER_ID + 1]));

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
            ['data-importer.ad-hoc-import.check.column.row', 'SKU', "'=Name", 'data-importer.ad-hoc-import.check.column.error'],
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

    public function testARunStillGoingCannotBeDiscarded(): void
    {
        $this->runs->save(AdHocRunFixtures::run(['fileId' => $this->fileId]));

        $this->expectException(InvalidArgumentException::class);

        $this->service(checks: 0)->discard($this->fileId);
    }
}
