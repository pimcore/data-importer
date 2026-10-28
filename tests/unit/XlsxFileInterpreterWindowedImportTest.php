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
use Symfony\Component\EventDispatcher\EventDispatcher;
use ZipArchive;

/**
 * The XLSX import reads the sheet in row windows instead of loading the whole workbook.
 * These tests pin the rows that reach processImportRow() to what the whole-workbook
 * toArray() path produced, and cover the formula rule the windowed read needs.
 *
 * A PreQueueRowEvent listener skips every row, so nothing is queued and the rows can be
 * asserted directly.
 */
class XlsxFileInterpreterWindowedImportTest extends Unit
{
    protected $tester;

    private const SHEET_NAME = 'Sheet1';

    private const SECOND_SHEET_NAME = 'Second';

    private const SHEET_XML_ENTRY = 'xl/worksheets/sheet1.xml';

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
        string $sheetName = self::SHEET_NAME
    ): XlsxFileInterpreter {
        $applicationLogger = $this->createMock(ApplicationLogger::class);
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
            new DeltaChecker(\Pimcore\Db::get()),
            new QueueService(),
            $applicationLogger
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
            'A1',
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
        $sheet->setCellValue('A1', 7);
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
     * Two populated rows at the start and one behind the given row number, nothing in between.
     */
    private function writeFixtureEndingAtRow(int $lastRowNumber): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([['first', 1], ['second', 2]], null, 'A1', true);
        $sheet->setCellValue('A' . $lastRowNumber, 'last');
        $sheet->setCellValue('B' . $lastRowNumber, 3);

        return $this->writeXlsx($spreadsheet);
    }

    private function writeTwoSheetFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([['h1', 'h2'], ['a', 'b'], ['c', 'd']], null, 'A1', true);
        $secondSheet = $spreadsheet->createSheet();
        $secondSheet->setTitle(self::SECOND_SHEET_NAME);
        $secondSheet->fromArray([['x', 'y'], ['1', '2']], null, 'A1', true);

        return $this->writeXlsx($spreadsheet);
    }

    public function testRowsMatchTheWholeWorkbookShape(): void
    {
        $this->createInterpreter()->interpretFile($this->writeShapeFixture());

        $this->assertSame(
            [
                ['sku', 'name', 'price', 'released', 'note'],
                ['A-1', 'First', '9.99', '45356', 'plain'],
                [null, null, null, null, null],
                ['B-2', null, '1234.5', null, null],
            ],
            $this->capturedRows,
            'the windowed read must hand over exactly what the whole-workbook toArray() produced'
        );
    }

    public function testRowsStayContinuousAcrossTheImportWindow(): void
    {
        $chunkRows = (new ReflectionClassConstant(XlsxFileInterpreter::class, 'IMPORT_CHUNK_ROWS'))->getValue();
        $lastRowNumber = (int)$chunkRows + 1;

        $this->createInterpreter()->interpretFile($this->writeFixtureEndingAtRow($lastRowNumber));

        $this->assertCount($lastRowNumber, $this->capturedRows, 'no row is emitted twice and none is skipped');
        $this->assertSame(['first', '1'], $this->capturedRows[0]);
        $this->assertSame(['second', '2'], $this->capturedRows[1]);
        $this->assertSame([null, null], $this->capturedRows[2], 'a row without a single cell stays empty');
        $this->assertSame(['last', '3'], $this->capturedRows[$lastRowNumber - 1], 'the row behind the window border');
    }

    public function testCachedFormulaResultIsUsed(): void
    {
        $this->createInterpreter()->interpretFile($this->writeFormulaFixtureWithDivergingCachedResult());

        $this->assertSame(
            [['7', '999']],
            $this->capturedRows,
            'the result cached in the file wins over recalculating =A1*6 to 42'
        );
        $this->assertSame([], $this->warnings, 'a cached result is not worth a warning');
    }

    public function testUncachedFormulaIsEvaluatedAndWarned(): void
    {
        $this->createInterpreter()->interpretFile($this->writeFormulaFixture(preCalculateFormulas: false));

        $this->assertSame([['7', '42']], $this->capturedRows);
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('Sheet1!B1', $this->warnings[0]['message']);
        $this->assertSame(
            PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            $this->warnings[0]['component'],
            'the Import Logs tab filters on this component'
        );
    }

    public function testUncachedFormulaWarningsAreCapped(): void
    {
        $interpreter = $this->createInterpreter();

        $interpreter->interpretFile($this->writeUncachedFormulaRows(130));

        $this->assertCount(51, $this->warnings, '50 cell warnings plus one closing line');
        $this->assertStringContainsString('Sheet1!B1', $this->warnings[0]['message']);
        $this->assertStringContainsString('Sheet1!B50', $this->warnings[49]['message']);
        $this->assertStringContainsString('80 further cells', $this->warnings[50]['message']);

        $this->warnings = [];
        $interpreter->interpretFile($this->writeUncachedFormulaRows(50));

        $this->assertCount(
            50,
            $this->warnings,
            'the counter resets per run, and 50 affected cells need no closing line'
        );
        $this->assertStringContainsString('Sheet1!B50', $this->warnings[49]['message']);
    }

    public function testSettingsAreHonoured(): void
    {
        $path = $this->writeTwoSheetFixture();

        $this->createInterpreter()->interpretFile($path);
        $this->assertSame([['h1', 'h2'], ['a', 'b'], ['c', 'd']], $this->capturedRows);

        $this->capturedRows = [];
        $this->createInterpreter(skipFirstRow: true)->interpretFile($path);
        $this->assertSame([['a', 'b'], ['c', 'd']], $this->capturedRows);

        $this->capturedRows = [];
        $this->createInterpreter(sheetName: self::SECOND_SHEET_NAME)->interpretFile($path);
        $this->assertSame([['x', 'y'], ['1', '2']], $this->capturedRows);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Sheet "Absent" not found in file.');
        $this->createInterpreter(sheetName: 'Absent')->interpretFile($path);
    }
}
