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
use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Event\PreQueueRowEvent;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Psr\Log\NullLogger;
use ReflectionClassConstant;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventDispatcher;
use ZipArchive;

/**
 * An XLSX sheet above the configured cell threshold is read in row windows, one below it is
 * loaded as a whole as before. These tests pin the rows that reach processImportRow() to what
 * the whole-workbook toArray() path produced, run every criterion on both sides of the
 * threshold, and cover the formula rule the windowed read needs.
 *
 * A PreQueueRowEvent listener skips every row, so nothing is queued and the rows can be
 * asserted directly. The interpretation step is driven directly rather than through
 * interpretFile(), whose tail writes the queue item count to the TmpStore: the rows are
 * captured from the event, so the queue, the delta check and the database add nothing here.
 */
class XlsxFileInterpreterWindowedImportTest extends Unit
{
    protected $tester;

    private const SHEET_NAME = 'Sheet1';

    private const SECOND_SHEET_NAME = 'Second';

    private const SHEET_XML_ENTRY = 'xl/worksheets/sheet1.xml';

    private const FIRST_CELL = 'A1';

    // Every sheet counts as above the threshold, so the import reads it in row windows.
    private const ALWAYS_WINDOWED = 0;

    // No sheet reaches the threshold, so the import loads the whole workbook as it did before.
    private const NEVER_WINDOWED = PHP_INT_MAX;

    private string $configName;

    /**
     * @var string[]
     */
    private array $tempFiles = [];

    /**
     * @var array<int, array>
     */
    private array $capturedRows = [];

    /**
     * @var array<int, array{message: string, component: ?string}>
     */
    private array $warnings = [];

    protected function _before(): void
    {
        $this->configName = uniqid('test_xlsx_window_');
        $this->capturedRows = [];
        $this->warnings = [];
    }

    protected function _after(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
    }

    private function createInterpreter(
        bool $skipFirstRow = false,
        string $sheetName = self::SHEET_NAME,
        int $windowedReadCellThreshold = self::ALWAYS_WINDOWED
    ): XlsxFileInterpreter {
        $applicationLogger = $this->createStub(ApplicationLogger::class);
        $applicationLogger->method('warning')->willReturnCallback(
            function (string $message, array $context = []): void {
                $this->warnings[] = ['message' => $message, 'component' => $context['component'] ?? null];
            }
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PreQueueRowEvent::class, function (PreQueueRowEvent $event): void {
            $this->capturedRows[] = $event->getOriginalRow();
            $event->skipRow();
        });

        $interpreter = new XlsxFileInterpreter(
            new DeltaChecker($this->createStub(Connection::class)),
            new QueueService(),
            $applicationLogger,
            $windowedReadCellThreshold
        );
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName($this->configName);
        $interpreter->setExecutionType(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $interpreter->setIdDataIndex(0);
        $interpreter->setDoDeltaCheck(false);
        $interpreter->setDoCleanup(false);
        $interpreter->setDoArchiveImportFile(false);
        $interpreter->setSettings(['skipFirstRow' => $skipFirstRow, 'sheetName' => $sheetName]);
        $interpreter->setEventDispatcher($dispatcher);

        return $interpreter;
    }

    /**
     * Runs the interpretation step the import performs, without the queue tail around it.
     */
    private function runImport(XlsxFileInterpreter $interpreter, string $path): void
    {
        (new ReflectionMethod($interpreter, 'doInterpretFileAndCallProcessRow'))->invoke($interpreter, $path);
    }

    /**
     * The coordinate the warning about a formula cell of column B names.
     */
    private function formulaCell(int $row): string
    {
        return self::SHEET_NAME . '!B' . $row;
    }

    private function warningMessage(int $index): string
    {
        return $this->warnings[$index]['message'];
    }

    private function warningComponent(int $index): ?string
    {
        return $this->warnings[$index]['component'];
    }

    private function writeXlsx(Spreadsheet $spreadsheet, bool $preCalculateFormulas = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'di_xlsx_') . '.xlsx';
        $writer = new XlsxWriter($spreadsheet);
        $writer->setPreCalculateFormulas($preCalculateFormulas);
        $writer->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Text, numeric, date, currency and interior-empty cells plus an interior fully empty row.
     */
    private function writeShapeFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray(
            [
                ['sku', 'name', 'price', 'released', 'note'],
                ['A-1', 'First', 9.99, null, 'plain'],
            ],
            null,
            self::FIRST_CELL,
            true
        );
        $sheet->setCellValue('D2', ExcelDate::formattedPHPToExcel(2024, 3, 5));
        $sheet->getStyle('C2')->getNumberFormat()->setFormatCode('#,##0.00 "EUR"');
        $sheet->getStyle('D2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->setCellValue('A4', 'B-2');
        $sheet->setCellValue('C4', 1234.5);

        return $this->writeXlsx($spreadsheet);
    }

    private function writeFormulaFixture(bool $preCalculateFormulas): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->setCellValue(self::FIRST_CELL, 7);
        $sheet->setCellValue('B1', '=A1*6');

        return $this->writeXlsx($spreadsheet, $preCalculateFormulas);
    }

    /**
     * Replaces the cached result the writer produced with one recalculation cannot reach,
     * so the two sources of a formula value can be told apart.
     */
    private function writeFormulaFixtureWithDivergingCachedResult(): string
    {
        $path = $this->writeFormulaFixture(preCalculateFormulas: true);

        $zip = new ZipArchive();
        $zip->open($path);
        $patched = preg_replace(
            '#(<f>[^<]*</f>)<v>[^<]*</v>#',
            '$1<v>999</v>',
            (string)$zip->getFromName(self::SHEET_XML_ENTRY),
            1,
            $count
        );
        $zip->addFromString(self::SHEET_XML_ENTRY, (string)$patched);
        $zip->close();

        $this->assertSame(1, $count, 'the fixture must carry exactly one cached formula result to replace');

        return $path;
    }

    private function writeUncachedFormulaRows(int $rowCount): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        for ($row = 1; $row <= $rowCount; $row++) {
            $sheet->setCellValue('A' . $row, $row);
            $sheet->setCellValue('B' . $row, '=A' . $row . '*2');
        }

        return $this->writeXlsx($spreadsheet, preCalculateFormulas: false);
    }

    /**
     * A formula the calculation engine cannot parse, so evaluating it throws rather than
     * returning an error string such as "#NAME?".
     */
    private function writeUncalculableFormulaRows(int $rowCount): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        for ($row = 1; $row <= $rowCount; $row++) {
            $sheet->setCellValue('A' . $row, 'row ' . $row);
            $sheet->getCell('B' . $row)->setValueExplicit('=1+', DataType::TYPE_FORMULA);
        }

        return $this->writeXlsx($spreadsheet, preCalculateFormulas: false);
    }

    /**
     * Two populated rows at the start and one behind the given row number, nothing in between.
     */
    private function writeFixtureEndingAtRow(int $lastRowNumber): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([['first', 1], ['second', 2]], null, self::FIRST_CELL, true);
        $sheet->setCellValue('A' . $lastRowNumber, 'last');
        $sheet->setCellValue('B' . $lastRowNumber, 3);

        return $this->writeXlsx($spreadsheet);
    }

    /**
     * An uncached formula in row 1 whose only reference sits behind the given row number.
     */
    private function writeFormulaReferencingRow(int $referencedRow): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->setCellValue(self::FIRST_CELL, '=B' . $referencedRow);
        $sheet->setCellValue('B' . $referencedRow, 123);

        return $this->writeXlsx($spreadsheet, preCalculateFormulas: false);
    }

    private function writeTwoSheetFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([['h1', 'h2'], ['a', 'b'], ['c', 'd']], null, self::FIRST_CELL, true);
        $secondSheet = $spreadsheet->createSheet();
        $secondSheet->setTitle(self::SECOND_SHEET_NAME);
        $secondSheet->fromArray([['x', 'y'], ['1', '2']], null, self::FIRST_CELL, true);

        return $this->writeXlsx($spreadsheet);
    }

    /**
     * Both sides of the cell threshold: the windowed read and the whole-workbook load have to
     * answer every criterion identically, so each case below runs twice.
     */
    public function provideThresholds(): array
    {
        return [
            'read in row windows' => [self::ALWAYS_WINDOWED],
            'whole workbook loaded at once' => [self::NEVER_WINDOWED],
        ];
    }

    /**
     * @dataProvider provideThresholds
     */
    public function testRowsMatchTheWholeWorkbookShape(int $threshold): void
    {
        $this->runImport(
            $this->createInterpreter(windowedReadCellThreshold: $threshold),
            $this->writeShapeFixture()
        );

        $this->assertSame(
            [
                ['sku', 'name', 'price', 'released', 'note'],
                ['A-1', 'First', '9.99', '45356', 'plain'],
                [null, null, null, null, null],
                ['B-2', null, '1234.5', null, null],
            ],
            $this->capturedRows,
            'both reads must hand over exactly what the whole-workbook toArray() produced'
        );
    }

    /**
     * @dataProvider provideThresholds
     */
    public function testRowsStayContinuousAcrossTheImportWindow(int $threshold): void
    {
        $lastRowNumber = $this->chunkRows() + 1;

        $this->runImport(
            $this->createInterpreter(windowedReadCellThreshold: $threshold),
            $this->writeFixtureEndingAtRow($lastRowNumber)
        );

        $this->assertCount($lastRowNumber, $this->capturedRows, 'no row is emitted twice and none is skipped');
        $this->assertSame(['first', '1'], $this->capturedRows[0]);
        $this->assertSame(['second', '2'], $this->capturedRows[1]);
        $this->assertSame([null, null], $this->capturedRows[2], 'a row without a single cell stays empty');
        $this->assertSame(['last', '3'], $this->capturedRows[$lastRowNumber - 1], 'the row behind the window border');
    }

    /**
     * @dataProvider provideThresholds
     */
    public function testCachedFormulaResultIsUsed(int $threshold): void
    {
        $this->runImport(
            $this->createInterpreter(windowedReadCellThreshold: $threshold),
            $this->writeFormulaFixtureWithDivergingCachedResult()
        );

        $this->assertSame(
            [['7', '999']],
            $this->capturedRows,
            'the result cached in the file wins over recalculating =A1*6 to 42'
        );
        $this->assertSame([], $this->warnings, 'a cached result is not worth a warning');
    }

    /**
     * @dataProvider provideThresholds
     */
    public function testUncachedFormulaIsEvaluatedAndWarned(int $threshold): void
    {
        $this->runImport(
            $this->createInterpreter(windowedReadCellThreshold: $threshold),
            $this->writeFormulaFixture(preCalculateFormulas: false)
        );

        $this->assertSame([['7', '42']], $this->capturedRows);
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString($this->formulaCell(1), $this->warningMessage(0));
        $this->assertSame(
            PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            $this->warningComponent(0),
            'the Import Logs tab filters on this component'
        );
    }

    /**
     * @dataProvider provideThresholds
     */
    public function testUncachedFormulaWarningsAreCapped(int $threshold): void
    {
        $interpreter = $this->createInterpreter(windowedReadCellThreshold: $threshold);

        $this->runImport($interpreter, $this->writeUncachedFormulaRows(130));

        $this->assertCount(51, $this->warnings, '50 cell warnings plus one closing line');
        $this->assertStringContainsString($this->formulaCell(1), $this->warningMessage(0));
        $this->assertStringContainsString($this->formulaCell(50), $this->warningMessage(49));
        $this->assertStringContainsString('80 further cells', $this->warningMessage(50));

        $this->warnings = [];
        $this->runImport($interpreter, $this->writeUncachedFormulaRows(50));

        $this->assertCount(
            50,
            $this->warnings,
            'the counter resets per run, and 50 affected cells need no closing line'
        );
        $this->assertStringContainsString($this->formulaCell(50), $this->warningMessage(49));
    }

    /**
     * A formula that throws instead of returning an error string empties its own cell and
     * nothing else - every later row still reaches the queue.
     *
     * @dataProvider provideThresholds
     */
    public function testAFormulaThatCannotBeCalculatedIsImportedEmptyAndTheImportContinues(int $threshold): void
    {
        $this->runImport(
            $this->createInterpreter(windowedReadCellThreshold: $threshold),
            $this->writeUncalculableFormulaRows(2)
        );

        $this->assertSame([['row 1', null], ['row 2', null]], $this->capturedRows);
        $this->assertCount(2, $this->warnings, 'a cell imported empty is never silent');
        $this->assertStringContainsString($this->formulaCell(1), $this->warningMessage(0));
        $this->assertStringContainsString('imported empty', $this->warningMessage(0));
        $this->assertSame(
            PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            $this->warningComponent(0),
            'the Import Logs tab filters on this component'
        );
    }

    /**
     * @dataProvider provideThresholds
     */
    public function testUncalculableFormulasCountTowardTheSameWarningCap(int $threshold): void
    {
        $this->runImport(
            $this->createInterpreter(windowedReadCellThreshold: $threshold),
            $this->writeUncalculableFormulaRows(60)
        );

        $this->assertCount(51, $this->warnings, '50 cell warnings plus one closing line');
        $this->assertStringContainsString($this->formulaCell(50), $this->warningMessage(49));
        $this->assertStringContainsString('10 further cells', $this->warningMessage(50));
    }

    /**
     * @dataProvider provideThresholds
     */
    public function testSettingsAreHonoured(int $threshold): void
    {
        $path = $this->writeTwoSheetFixture();

        $this->runImport($this->createInterpreter(windowedReadCellThreshold: $threshold), $path);
        $this->assertSame([['h1', 'h2'], ['a', 'b'], ['c', 'd']], $this->capturedRows);

        $this->capturedRows = [];
        $this->runImport(
            $this->createInterpreter(skipFirstRow: true, windowedReadCellThreshold: $threshold),
            $path
        );
        $this->assertSame([['a', 'b'], ['c', 'd']], $this->capturedRows);

        $this->capturedRows = [];
        $this->runImport(
            $this->createInterpreter(sheetName: self::SECOND_SHEET_NAME, windowedReadCellThreshold: $threshold),
            $path
        );
        $this->assertSame([['x', 'y'], ['1', '2']], $this->capturedRows);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Sheet "Absent" not found in file.');
        $this->runImport($this->createInterpreter(sheetName: 'Absent', windowedReadCellThreshold: $threshold), $path);
    }

    /**
     * The threshold counts cells, not rows or bytes, and a sheet exactly on it still takes the
     * whole-workbook read - only a sheet above it is read in windows.
     */
    public function testTheCellCountDecidesWhichReadIsUsed(): void
    {
        $lastRowNumber = $this->chunkRows() + 1;
        $path = $this->writeFixtureEndingAtRow($lastRowNumber);
        $cellCount = $lastRowNumber * 2;

        $this->runImport($this->createInterpreter(windowedReadCellThreshold: $cellCount), $path);
        $wholeWorkbookRows = $this->capturedRows;

        $this->capturedRows = [];
        $this->runImport($this->createInterpreter(windowedReadCellThreshold: $cellCount - 1), $path);

        $this->assertSame(
            $wholeWorkbookRows,
            $this->capturedRows,
            'the read picked on the threshold boundary must not change a single row'
        );
        $this->assertCount($lastRowNumber, $this->capturedRows);
    }

    /**
     * The one cell of the decision matrix where the two reads genuinely differ, and the reason
     * the uncached formula warning exists: a window cannot see the rows outside it.
     */
    public function testAnUncachedFormulaReachingOutsideTheWindowOnlyResolvesOnTheWholeWorkbook(): void
    {
        $referencedRow = $this->chunkRows() + 1;
        $path = $this->writeFormulaReferencingRow($referencedRow);

        $this->runImport($this->createInterpreter(windowedReadCellThreshold: self::NEVER_WINDOWED), $path);
        $this->assertSame('123', $this->capturedRows[0][0], 'the whole-workbook read sees the referenced row');

        $this->capturedRows = [];
        $this->runImport($this->createInterpreter(windowedReadCellThreshold: self::ALWAYS_WINDOWED), $path);
        $this->assertSame('0', $this->capturedRows[0][0], 'the window cannot reach the referenced row');
        $this->assertStringContainsString(
            'Sheet1!A1',
            $this->warningMessage(0),
            'and that is exactly what the warning announces'
        );
    }

    private function chunkRows(): int
    {
        return (int)(new ReflectionClassConstant(XlsxFileInterpreter::class, 'IMPORT_CHUNK_ROWS'))->getValue();
    }
}
