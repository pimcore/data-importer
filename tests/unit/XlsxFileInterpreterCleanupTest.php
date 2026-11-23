<?php declare(strict_types=1);

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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Model\Element\ElementInterface;
use Psr\Log\NullLogger;

/**
 * An XLSX import with an active cleanup strategy turns every identifier the source does not carry
 * into a cleanup queue item, which deletes or unpublishes the existing element. A formula cell the
 * read could not resolve puts a value in the row that the source does not hold - nothing at all, or
 * "#REF!" where a window cannot reach the worksheet the formula points at - so an identifier column
 * built from such a formula would hand an existing element to that cleanup.
 *
 * These tests drive the whole interpretFile() with cleanup enabled, because the cleanup step sits in
 * its tail, and read the result from the cleanup queue items it leaves behind.
 */
class XlsxFileInterpreterCleanupTest extends Unit
{
    protected $tester;

    private const SHEET_NAME = 'Sheet1';

    private const SECOND_SHEET_NAME = 'Second';

    // Every sheet counts as above the threshold, so the import reads it in row windows.
    private const ALWAYS_WINDOWED = 0;

    // No sheet reaches the threshold, so the import loads the whole workbook.
    private const NEVER_WINDOWED = PHP_INT_MAX;

    private string $configName;

    /**
     * @var string[]
     */
    private array $tempFiles = [];

    /**
     * @var string[]
     */
    private array $errors = [];

    protected function _before(): void
    {
        $this->configName = uniqid('test_xlsx_cleanup_');
        $this->errors = [];
    }

    protected function _after(): void
    {
        $this->drainQueue();

        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
    }

    private function createInterpreter(int $windowedReadCellThreshold): XlsxFileInterpreter
    {
        $applicationLogger = $this->createStub(ApplicationLogger::class);
        $applicationLogger->method('error')->willReturnCallback(
            function (string $message): void {
                $this->errors[] = $message;
            }
        );

        $interpreter = new XlsxFileInterpreter(
            new DeltaChecker(\Pimcore\Db::get()),
            new QueueService(),
            $applicationLogger,
            $windowedReadCellThreshold
        );
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName($this->configName);
        $interpreter->setExecutionType(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $interpreter->setIdDataIndex(0);
        $interpreter->setDoDeltaCheck(false);
        $interpreter->setDoCleanup(true);
        $interpreter->setResolver($this->createResolver());
        $interpreter->setDoArchiveImportFile(false);
        $interpreter->setSettings(['skipFirstRow' => false, 'sheetName' => self::SHEET_NAME]);

        return $interpreter;
    }

    /**
     * A resolver whose loading strategy pretends A-1 and B-2 already exist, so what cleanup would
     * do to them can be read from the cleanup queue items alone.
     */
    private function createResolver(): Resolver
    {
        $resolver = new Resolver();
        $resolver->setLoadingStrategy(new class () implements LoadStrategyInterface {
            public function loadElement(array $inputData): ?ElementInterface
            {
                return null;
            }

            public function loadElementByIdentifier($identifier): ?ElementInterface
            {
                return null;
            }

            public function extractIdentifierFromData(array $inputData)
            {
                return $inputData[0] ?? null;
            }

            public function loadFullIdentifierList(): array
            {
                return ['A-1', 'B-2'];
            }

            public function setDataObjectClassId($dataObjectClassId): void
            {
                // the stub resolves identifiers from the row data alone
            }

            public function setSettings(array $settings): void
            {
                // the stub needs no configuration
            }
        });

        return $resolver;
    }

    private function writeXlsx(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'di_xlsx_') . '.xlsx';
        $writer = new XlsxWriter($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Both existing identifiers are in the source, the second one spelled out rather than computed.
     */
    private function writeCompleteFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([['A-1', 'First'], ['B-2', 'Second']], null, 'A1', true);

        return $this->writeXlsx($spreadsheet);
    }

    /**
     * The identifier of the second row is a formula phpspreadsheet cannot parse, so the cell is
     * imported empty on either read.
     */
    private function writeUncalculableIdentifierFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([['A-1', 'First'], [null, 'Second']], null, 'A1', true);
        $sheet->getCell('A2')->setValueExplicit('=1+', DataType::TYPE_FORMULA);

        return $this->writeXlsx($spreadsheet);
    }

    /**
     * The identifier of the second row is an uncached formula pointing at the second worksheet,
     * which only the whole-workbook read loads.
     */
    private function writeCrossSheetIdentifierFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([['A-1', 'First'], [null, 'Second']], null, 'A1', true);
        $sheet->setCellValue('A2', '=' . self::SECOND_SHEET_NAME . '!A1');
        $secondSheet = $spreadsheet->createSheet();
        $secondSheet->setTitle(self::SECOND_SHEET_NAME);
        $secondSheet->setCellValue('A1', 'B-2');

        return $this->writeXlsx($spreadsheet);
    }

    /**
     * @return string[] the identifiers this run handed to the cleanup step
     */
    private function cleanupIdentifiers(): array
    {
        $queueService = new QueueService();
        $identifiers = [];
        foreach ($queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL) as $id) {
            $entry = $queueService->getQueueEntryById($id);
            if (
                ($entry['configName'] ?? null) === $this->configName
                && ($entry['jobType'] ?? null) === ImportProcessingService::JOB_TYPE_CLEANUP
            ) {
                $identifiers[] = $entry['data'];
            }
        }

        return $identifiers;
    }

    private function drainQueue(): void
    {
        $queueService = new QueueService();
        foreach ($queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL) as $id) {
            $entry = $queueService->getQueueEntryById($id);
            if (($entry['configName'] ?? null) === $this->configName) {
                $queueService->markQueueEntryAsProcessed($id);
            }
        }
    }

    public function provideThresholds(): array
    {
        return [
            'read in row windows' => [self::ALWAYS_WINDOWED],
            'whole workbook loaded at once' => [self::NEVER_WINDOWED],
        ];
    }

    /**
     * The regression: an identifier the read emptied used to leave its existing element looking
     * removed from the source, and cleanup would have deleted or unpublished it.
     *
     * @dataProvider provideThresholds
     */
    public function testAnIdentifierEmptiedByAnUnresolvableFormulaStopsCleanup(int $threshold): void
    {
        $this->assertTrue(
            $this->createInterpreter($threshold)->interpretFile($this->writeUncalculableIdentifierFixture())
        );

        $this->assertSame(
            [],
            $this->cleanupIdentifiers(),
            'B-2 lost its identifier to a formula, which is no evidence that it left the source'
        );
        $this->assertCount(1, $this->errors, 'and skipping the cleanup is never silent');
        $this->assertStringContainsString('Not cleaning up', $this->errors[0]);
    }

    /**
     * The windowed read cannot see the worksheet the identifier formula points at, so the row
     * arrives under "#REF!" - an identifier the source does not hold either.
     */
    public function testAnIdentifierMisreadByTheWindowedReadStopsCleanup(): void
    {
        $this->createInterpreter(self::ALWAYS_WINDOWED)->interpretFile($this->writeCrossSheetIdentifierFixture());

        $this->assertSame([], $this->cleanupIdentifiers());
        $this->assertCount(1, $this->errors);
    }

    /**
     * The same file below the threshold is read with its second worksheet in place, so the formula
     * resolves and cleanup runs on identifiers that are trustworthy again.
     */
    public function testTheWholeWorkbookReadResolvesTheSameIdentifierAndCleansUp(): void
    {
        $this->createInterpreter(self::NEVER_WINDOWED)->interpretFile($this->writeCrossSheetIdentifierFixture());

        $this->assertSame(
            [],
            $this->cleanupIdentifiers(),
            'the formula resolved to B-2, so both existing elements are accounted for'
        );
        $this->assertSame([], $this->errors, 'nothing was left unread, so cleanup ran rather than being skipped');
    }

    /**
     * Cleanup still does its job: a file without an unresolved formula is trusted as before.
     *
     * @dataProvider provideThresholds
     */
    public function testAnIdentifierMissingFromAFullyReadSourceIsStillCleanedUp(int $threshold): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([['A-1', 'First']], null, 'A1', true);

        $this->createInterpreter($threshold)->interpretFile($this->writeXlsx($spreadsheet));

        $this->assertSame(['B-2'], $this->cleanupIdentifiers(), 'B-2 is genuinely gone from the source');
        $this->assertSame([], $this->errors);
    }

    /**
     * @dataProvider provideThresholds
     */
    public function testACompleteSourceLeavesBothElementsAlone(int $threshold): void
    {
        $this->createInterpreter($threshold)->interpretFile($this->writeCompleteFixture());

        $this->assertSame([], $this->cleanupIdentifiers());
        $this->assertSame([], $this->errors);
    }
}
