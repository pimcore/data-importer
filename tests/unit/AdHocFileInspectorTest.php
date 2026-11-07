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
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\CsvDelimiterDetector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\RefusalReason;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxColumnDefinitions;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;

class AdHocFileInspectorTest extends Unit
{
    private const string CSV_HEADER = "sku,name\n";

    private const string CSV_ONE_ROW = "sku,name\nA-1,Chair\n";

    private const string FIRST_SHEET = 'xl/worksheets/sheet1.xml';

    /**
     * @var string[]
     */
    private array $files = [];

    protected function _after(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testACsvIsReadWithTheDetectedDelimiter(): void
    {
        $path = $this->csv(
            "Artikelnr;Bezeichnung;Preis\nNH-1001;Trail jacket;\"189,90\"\nNH-1002;Rain pants;\"99,00\"\n"
        );

        $inspection = $this->inspector()->inspect($path, FileType::Csv, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(';', $inspection->detectedDelimiter);
        $this->assertSame(';', $inspection->settings['delimiter']);
        $this->assertSame('"', $inspection->settings['enclosure']);
        $this->assertSame('\\', $inspection->settings['escape']);
        $this->assertSame(2, $inspection->rowCount);
        $this->assertSame(
            [
                ['dataIndex' => 'Artikelnr', 'label' => 'Artikelnr', 'position' => 0],
                ['dataIndex' => 'Bezeichnung', 'label' => 'Bezeichnung', 'position' => 1],
                ['dataIndex' => 'Preis', 'label' => 'Preis', 'position' => 2],
            ],
            $inspection->columns
        );
        $this->assertSame(
            [['NH-1001', 'Trail jacket', '189,90'], ['NH-1002', 'Rain pants', '99,00']],
            $inspection->firstRows
        );
    }

    public function testOnlyTheFirstThreeRowsAreReturnedButAllAreCounted(): void
    {
        $rows = array_map(static fn (int $i): string => "A-$i,Name $i", range(1, 7));
        $path = $this->csv(self::CSV_HEADER . implode("\n", $rows) . "\n");

        $inspection = $this->inspector()->inspect($path, FileType::Csv, new SettingsInput());

        $this->assertSame(7, $inspection->rowCount);
        $this->assertSame([['A-1', 'Name 1'], ['A-2', 'Name 2'], ['A-3', 'Name 3']], $inspection->firstRows);
    }

    public function testTheByteOrderMarkIsNoPartOfTheFirstColumn(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv("\xEF\xBB\xBFsku,name\nA-1,Chair\n"),
            FileType::Csv,
            new SettingsInput()
        );

        $this->assertSame('sku', $inspection->columns[0]['dataIndex']);
    }

    public function testChosenCsvSettingsWinOverTheDetectedOnes(): void
    {
        $path = $this->csv("'a;b';c\n'd;e';f\n");

        $inspection = $this->inspector()->inspect(
            $path,
            FileType::Csv,
            new SettingsInput(delimiter: ';', enclosure: "'", hasHeaderRow: false)
        );

        $this->assertNull($inspection->refusal);
        $this->assertSame("'", $inspection->settings['enclosure']);
        $this->assertFalse($inspection->settings['hasHeaderRow']);
        $this->assertSame(2, $inspection->rowCount);
        $this->assertSame([['a;b', 'c'], ['d;e', 'f']], $inspection->firstRows);
    }

    public function testWithoutAHeaderRowTheColumnsAreKnownByTheirPosition(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv("A-1,Chair\nA-2,Table\n"),
            FileType::Csv,
            new SettingsInput(hasHeaderRow: false)
        );

        $this->assertSame(
            [['dataIndex' => '0', 'label' => '0'], ['dataIndex' => '1', 'label' => '1']],
            $inspection->columns
        );
        $this->assertSame(2, $inspection->rowCount);
    }

    public function testWithoutAHeaderRowALaterRowMayBeTheWidestAndEarlierRowsAreFilledUp(): void
    {
        $path = $this->csv("A-1\nA-2,Chair\nA-3,Desk,12\n");

        $inspection = $this->inspector()->inspect($path, FileType::Csv, new SettingsInput(hasHeaderRow: false));

        $this->assertNull($inspection->refusal);
        $this->assertSame(
            [
                ['dataIndex' => '0', 'label' => '0'],
                ['dataIndex' => '1', 'label' => '1'],
                ['dataIndex' => '2', 'label' => '2'],
            ],
            $inspection->columns
        );
        $this->assertSame(
            [['A-1', null, null], ['A-2', 'Chair', null], ['A-3', 'Desk', '12']],
            $inspection->firstRows
        );
    }

    public function testAHeaderColumnKeepsItsPlaceInTheFileAfterAnUnnamedOne(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv("sku,,name\nA-1,x,Chair\n"),
            FileType::Csv,
            new SettingsInput()
        );

        $this->assertSame(
            [
                ['dataIndex' => 'sku', 'label' => 'sku', 'position' => 0],
                ['dataIndex' => 'name', 'label' => 'name', 'position' => 2],
            ],
            $inspection->columns
        );
        $this->assertSame([['A-1', 'Chair']], $inspection->firstRows);
    }

    public function testAHeaderOnlyCsvHasItsColumnsAndNoRows(): void
    {
        $inspection = $this->inspector()->inspect($this->csv(self::CSV_HEADER), FileType::Csv, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(0, $inspection->rowCount);
        $this->assertCount(2, $inspection->columns);
    }

    public function testACsvUpToTheLimitIsTakenAndOneRowMoreIsRefusedWithItsCount(): void
    {
        $atLimit = $this->inspector()->inspect(
            $this->csvWithRows(FileInspector::MAX_ROWS),
            FileType::Csv,
            new SettingsInput()
        );
        $this->assertNull($atLimit->refusal);
        $this->assertSame(FileInspector::MAX_ROWS, $atLimit->rowCount);

        $over = $this->inspector()->inspect(
            $this->csvWithRows(FileInspector::MAX_ROWS + 1),
            FileType::Csv,
            new SettingsInput()
        );
        $this->assertSame(RefusalReason::TooManyRows, $over->refusal);
        $this->assertSame(FileInspector::MAX_ROWS + 1, $over->rowCount);
        $this->assertSame([], $over->columns);
    }

    public function testACsvRowNotFittingTheHeaderIsUnreadableButKeepsItsSettings(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv("sku,name\nA-1,Chair\nA-2\n"),
            FileType::Csv,
            new SettingsInput()
        );

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
        $this->assertSame(',', $inspection->settings['delimiter']);
    }

    public function testACsvNotInUtf8IsUnreadable(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv("sku,name\nA-1,gr\xFCn\n"),
            FileType::Csv,
            new SettingsInput()
        );

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testTwoColumnsOfOneNameAreUnreadableBecauseAnImportWouldKeepOnlyOne(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv("sku,name,name\nA-1,Chair,Table\n"),
            FileType::Csv,
            new SettingsInput()
        );

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testUnnamedCsvColumnsAreLeftOut(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv("sku,name,,\nA-1,Chair,,\n"),
            FileType::Csv,
            new SettingsInput()
        );

        $this->assertNull($inspection->refusal);
        $this->assertSame(['sku', 'name'], array_column($inspection->columns, 'dataIndex'));
        $this->assertSame([['A-1', 'Chair']], $inspection->firstRows);
    }

    public function testAnEnclosureOfSeveralBytesIsUnreadableInsteadOfAnError(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv(self::CSV_ONE_ROW),
            FileType::Csv,
            new SettingsInput(enclosure: '“')
        );

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testAnXlsxIsReadFromItsFirstSheetByDefaultAndListsItsSheets(): void
    {
        $path = $this->xlsx([
            'Products' => [['Artikelnr', 'Preis'], ['NH-1001', 189.9], ['NH-1002', 99]],
            'Notes' => [['Note'], ['one'], ['two'], ['three']],
        ]);

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(['Products', 'Notes'], $inspection->sheets);
        $this->assertSame('Products', $inspection->settings['sheetName']);
        $this->assertSame(2, $inspection->rowCount);
        $this->assertSame(
            [['dataIndex' => '0', 'label' => 'Artikelnr'], ['dataIndex' => '1', 'label' => 'Preis']],
            $inspection->columns
        );
        $this->assertSame('NH-1001', $inspection->firstRows[0][0]);
        $this->assertNull($inspection->detectedDelimiter);
    }

    public function testAnXlsxReadsTheChosenSheetWithAndWithoutAHeaderRow(): void
    {
        $path = $this->xlsx(['Products' => [['a']], 'Notes' => [['Note'], ['one'], ['two']]]);

        $withHeader = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput(sheetName: 'Notes'));
        $this->assertSame('Note', $withHeader->columns[0]['label']);
        $this->assertSame(2, $withHeader->rowCount);

        $withoutHeader = $this->inspector()->inspect(
            $path,
            FileType::Xlsx,
            new SettingsInput(hasHeaderRow: false, sheetName: 'Notes')
        );
        $this->assertSame('0', $withoutHeader->columns[0]['label']);
        $this->assertSame(3, $withoutHeader->rowCount);
        $this->assertSame([['Note'], ['one'], ['two']], $withoutHeader->firstRows);
    }

    public function testUnnamedXlsxColumnsAreLeftOutAndTheOthersKeepTheirPosition(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->xlsx(['Products' => [['sku', null, 'name'], ['A-1', 'x', 'Chair']]]),
            FileType::Xlsx,
            new SettingsInput()
        );

        $this->assertSame(['0', '2'], array_column($inspection->columns, 'dataIndex'));
        $this->assertSame([['A-1', 'Chair']], $inspection->firstRows);
    }

    public function testAnXlsxSheetOverTheLimitIsRefusedWithItsCountAndTheOtherSheetsStayChoosable(): void
    {
        $path = $this->xlsx([
            'Big' => array_fill(0, FileInspector::MAX_ROWS + 2, ['value']),
            'Small' => [['sku'], ['A-1']],
        ]);

        $big = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());
        $this->assertSame(RefusalReason::TooManyRows, $big->refusal);
        $this->assertSame(FileInspector::MAX_ROWS + 1, $big->rowCount);
        $this->assertSame(['Big', 'Small'], $big->sheets);

        $small = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput(sheetName: 'Small'));
        $this->assertNull($small->refusal);
        $this->assertSame(1, $small->rowCount);
    }

    public function testAnUnknownSheetIsUnreadableAndTheSheetsStayChoosable(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->xlsx(['Products' => [['a']]]),
            FileType::Xlsx,
            new SettingsInput(sheetName: 'Gone')
        );

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
        $this->assertSame(['Products'], $inspection->sheets);
    }

    public function testATextFileNamedXlsxIsNotReadable(): void
    {
        $path = $this->file(self::CSV_ONE_ROW, 'xlsx');

        $this->assertFalse($this->inspector()->isReadable($path, FileType::Xlsx));
        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());
        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testAWorkbookWithABrokenSheetIsUnreadableInsteadOfAnError(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $this->replaceInSheet($path, null, '<worksheet><sheetData><row><c');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testAWorkbookWithABrokenWorkbookPartIsUnreadableInsteadOfAnError(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $zip = new \ZipArchive();
        $zip->open($path);
        $zip->addFromString('xl/workbook.xml', '<workbook><sheets><sheet');
        $zip->close();

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());
        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testAWorkbookWithAPartOnlyTheLoadReadsBrokenIsUnreadableInsteadOfAnError(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $zip = new \ZipArchive();
        $zip->open($path);
        $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t');
        $zip->close();

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());
        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testAnXlsxUpToTheLimitIsTakenWithItsCount(): void
    {
        $path = $this->xlsx(['Products' => array_fill(0, FileInspector::MAX_ROWS + 1, ['value'])]);

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(FileInspector::MAX_ROWS, $inspection->rowCount);
    }

    public function testAWorkbookWithoutRowNumbersIsHeldToTheLimitByTheRowsItHas(): void
    {
        $path = $this->xlsx(['Products' => array_fill(0, FileInspector::MAX_ROWS + 2, ['value'])]);
        $this->removeRowNumbers($path);

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertSame(RefusalReason::TooManyRows, $inspection->refusal);
        $this->assertSame(FileInspector::MAX_ROWS + 1, $inspection->rowCount);
        $this->assertSame(['Products'], $inspection->sheets);
        $this->assertSame([], $inspection->columns);
        $this->assertSame([], $inspection->firstRows);
    }

    public function testAWorkbookWithoutRowNumbersUpToTheLimitIsTaken(): void
    {
        $path = $this->xlsx(['Products' => array_fill(0, FileInspector::MAX_ROWS + 1, ['value'])]);
        $this->removeRowNumbers($path);

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(FileInspector::MAX_ROWS, $inspection->rowCount);
    }

    public function testASparseSheetWhoseFullGridIsTooLargeIsRefusedBeforeItsRowsAreRead(): void
    {
        $path = $this->sparseXlsx('XFD5001');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertSame(RefusalReason::TooManyCells, $inspection->refusal);
        $this->assertSame(['Products'], $inspection->sheets);
        $this->assertSame([], $inspection->columns);
        $this->assertSame([], $inspection->firstRows);
    }

    public function testASparseSheetIsRefusedWhateverNumbersItsRowsClaim(): void
    {
        $path = $this->sparseXlsx('XFD5001');
        $this->replaceInSheet($path, '<row r="5001"', '<row r="2"');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertSame(RefusalReason::TooManyCells, $inspection->refusal);

        $withoutNumbers = $this->sparseXlsx('XFD5001');
        $this->removeRowNumbers($withoutNumbers);

        $inspection = $this->inspector()->inspect($withoutNumbers, FileType::Xlsx, new SettingsInput());

        $this->assertSame(RefusalReason::TooManyCells, $inspection->refusal);
    }

    public function testARowClaimingAFalseNumberIsHeldToTheRowLimitByItsCells(): void
    {
        $path = $this->sparseXlsx('A5003');
        $this->replaceInSheet($path, '<row r="5003"', '<row r="2"');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertSame(RefusalReason::TooManyRows, $inspection->refusal);
        $this->assertSame(5002, $inspection->rowCount);
    }

    /**
     * @dataProvider cellsTheMeasurementCannotPlace
     */
    public function testACellTheMeasurementCannotPlaceIsRefusedBeforeItsRowsAreRead(string $cells): void
    {
        $path = $this->xlsx(['Products' => [['sku']]]);
        $this->replaceInSheet(
            $path,
            null,
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . "<row r=\"1\">$cells</row></sheetData></worksheet>"
        );

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput(hasHeaderRow: false));

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
        $this->assertSame(0, $inspection->rowCount);
        $this->assertSame([], $inspection->columns);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cellsTheMeasurementCannotPlace(): array
    {
        return [
            'a row past the row limit' => ['<c r="A$6000"><v>1</v></c>'],
            'a row within the limits' => ['<c r="A$2"><v>1</v></c>'],
            'a row past the cell limit' => ['<c r="XFD1"><v>1</v></c><c r="A$200000"><v>1</v></c>'],
            'a marked column' => ['<c r="$A1"><v>1</v></c>'],
            'a marked column and row' => ['<c r="$A$6000"><v>1</v></c>'],
            'row zero' => ['<c r="A0"><v>1</v></c>'],
        ];
    }

    public function testAFullGridOfExactlyTheLimitIsReadAndOneColumnMoreIsRefused(): void
    {
        $atLimit = $this->inspector()->inspect(
            $this->sparseXlsx('GR5000'),
            FileType::Xlsx,
            new SettingsInput(hasHeaderRow: false)
        );

        $this->assertNull($atLimit->refusal);
        $this->assertSame(5000, $atLimit->rowCount);
        $this->assertCount(200, $atLimit->columns);

        $over = $this->inspector()->inspect(
            $this->sparseXlsx('GS5000'),
            FileType::Xlsx,
            new SettingsInput(hasHeaderRow: false)
        );

        $this->assertSame(RefusalReason::TooManyCells, $over->refusal);
    }

    public function testASingleWideRowIsRead(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->sparseXlsx('XFD2'),
            FileType::Xlsx,
            new SettingsInput()
        );

        $this->assertNull($inspection->refusal);
        $this->assertSame(1, $inspection->rowCount);
    }

    public function testARowThatOnlyCarriesFormattingDoesNotCountForTheLimit(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1'], ['A-2']]]);
        $this->replaceInSheet($path, '</sheetData>', '<row r="6000" s="1" customFormat="1"/></sheetData>');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(2, $inspection->rowCount);
    }

    public function testARowThatOnlyCarriesFormattingDoesNotCountBesideAColumnDefinition(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1'], ['A-2']]]);
        $this->replaceInSheet(
            $path,
            '</sheetData>',
            '<row r="6000" s="1" customFormat="1"/></sheetData>'
        );
        $this->setColumnDefinitions($path, '<col min="1" max="1" width="11" customWidth="1"/>');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(2, $inspection->rowCount);
    }

    public function testAColumnDefinitionWiderThanTheCellsAddsNoColumnsAndNoCells(): void
    {
        $rows = array_fill(0, 1000, ['a', 'b', 'c']);
        $path = $this->xlsx(['Products' => $rows]);
        $this->setColumnDefinitions($path, '<col min="1" max="1024" width="11" customWidth="1"/>');

        $withHeader = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($withHeader->refusal);
        $this->assertSame(999, $withHeader->rowCount);
        $this->assertCount(3, $withHeader->columns);

        $withoutHeader = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput(hasHeaderRow: false));

        $this->assertNull($withoutHeader->refusal);
        $this->assertSame(1000, $withoutHeader->rowCount);
        $this->assertCount(3, $withoutHeader->columns);
        $this->assertSame(['a', 'b', 'c'], $withoutHeader->firstRows[0]);
    }

    public function testATinySheetOfEmptyRowsAndAWideColumnDefinitionReachesOnlyItsCells(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $this->replaceInSheet($path, '</sheetData>', str_repeat('<row/>', 20000) . '</sheetData>');
        $this->setColumnDefinitions($path, '<col min="1" max="16383" width="11"/>');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(1, $inspection->rowCount);
        $this->assertCount(1, $inspection->columns);
    }

    public function testAReadFilterIsAskedOnlyAboutTheCells(): void
    {
        $path = $this->xlsx(['Products' => [['sku', 'name'], ['A-1', 'Chair']]]);
        $this->replaceInSheet($path, '</sheetData>', '<row/><row/><row/></sheetData>');
        $this->setColumnDefinitions($path, '<col min="1" max="50" width="11"/>');
        $filter = new class() implements IReadFilter {
            public int $calls = 0;

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                ++$this->calls;

                return false;
            }
        };

        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        XlsxColumnDefinitions::ignoreOn($reader);
        $reader->setReadFilter($filter);
        $reader->load($path);

        $this->assertSame(4, $filter->calls);
    }

    public function testAColumnDefinitionRunningBackwardsIsNoReasonToRefuseASheet(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $this->setColumnDefinitions($path, '<col min="5" max="1" width="11"/>');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(1, $inspection->rowCount);
    }

    public function testAValueThatLooksLikeAColumnDefinitionStays(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['cols']]]);
        $this->replaceInSheet(
            $path,
            '</sheetData>',
            '<row r="3"><c r="A3" t="inlineStr"><is><t><![CDATA[<cols>x</cols>]]></t></is></c></row></sheetData>'
        );

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame([['cols'], ['<cols>x</cols>']], $inspection->firstRows);
    }

    public function testBrokenXmlIsLeftForTheReaderToReport(): void
    {
        $reader = new XlsxReader();
        XlsxColumnDefinitions::ignoreOn($reader);

        $broken = '<worksheet><cols><col min="1" max="1"/>';

        $this->assertSame($broken, $reader->getSecurityScannerOrThrow()->scan($broken));
    }

    public function testEmptyCellsCountForTheLimitBecauseAnImportReceivesThemAsRows(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $this->replaceInSheet($path, '</sheetData>', '<row r="6000"><c r="A6000" s="0"/></row></sheetData>');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertSame(RefusalReason::TooManyRows, $inspection->refusal);
        $this->assertSame(5999, $inspection->rowCount);
    }

    public function testOnlyTheChosenSheetIsLoaded(): void
    {
        // without a stored result the formula could only be calculated with the other sheet loaded
        $path = $this->xlsxWithAFormulaOnAnotherSheet(storeResults: false);

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertNotSame('5', $inspection->firstRows[0][1]);
    }

    public function testAFormulaShowsItsStoredResultAlthoughItsSheetIsNotLoaded(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->xlsxWithAFormulaOnAnotherSheet(storeResults: true),
            FileType::Xlsx,
            new SettingsInput()
        );

        $this->assertSame([['A-1', '5', '12', '7x', 'TRUE', '005']], $inspection->firstRows);
    }

    public function testAnImportStillCalculatesFormulasFromOtherSheets(): void
    {
        $factory = new InterpreterFactory([
            'xlsx' => (new \ReflectionClass(XlsxFileInterpreter::class))->newInstanceWithoutConstructor(),
        ]);

        $rows = (new SourceFileReader($factory))->readRows(
            ['type' => 'xlsx', 'settings' => ['skipFirstRow' => true, 'sheetName' => 'Products']],
            $this->xlsxWithAFormulaOnAnotherSheet(storeResults: false)
        );

        $this->assertSame([['A-1', '5', '12', '7x', 'TRUE', '005']], [...$rows]);
    }

    public function testAWorkbookWithoutItsWorkbookPartIsUnreadableInsteadOfAnError(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $zip = new \ZipArchive();
        $zip->open($path);
        $zip->deleteName('xl/workbook.xml');
        $zip->close();

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());
        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testAnXlsxIsReadable(): void
    {
        $this->assertTrue($this->inspector()->isReadable($this->xlsx(['Products' => [['a']]]), FileType::Xlsx));
    }

    public function testACsvIsReadableAndABinaryFileNamedCsvIsNot(): void
    {
        $this->assertTrue($this->inspector()->isReadable($this->csv(self::CSV_ONE_ROW), FileType::Csv));
        $this->assertFalse($this->inspector()->isReadable($this->xlsx(['Products' => [['a']]], 'csv'), FileType::Csv));
    }

    public function testTheFileTypeComesFromTheExtensionInAnyCase(): void
    {
        $this->assertSame(FileType::Csv, FileType::fromFileName('prices.CSV'));
        $this->assertSame(FileType::Xlsx, FileType::fromFileName('northhike.autumn.xlsx'));
        $this->assertNull(FileType::fromFileName('northhike.xls'));
        $this->assertNull(FileType::fromFileName('northhike'));
    }

    /**
     * @dataProvider delimiters
     */
    public function testTheDelimiterIsDetected(string $content, string $expected): void
    {
        $this->assertSame($expected, (new CsvDelimiterDetector())->detect($this->csv($content)));
    }

    public static function delimiters(): iterable
    {
        yield 'semicolon' => ["a;b;c\n1;2;3\n", ';'];
        yield 'comma' => ["a,b,c\n1,2,3\n", ','];
        yield 'tab' => ["a\tb\n1\t2\n", "\t"];
        yield 'pipe' => ["a|b\n1|2\n", '|'];
        yield 'commas inside the values of a semicolon file' => ["a;b\n\"1,5\";2\n\"3,5\";4\n", ';'];
        yield 'the delimiter that keeps the column count wins' => ["a;b,c\n1;2\n3;4\n", ';'];
        yield 'windows line ends' => ["a;b\r\n1;2\r\n", ';'];
        yield 'one column falls back to the comma' => ["a\n1\n", ','];
    }

    public function testAnEmptyFileFallsBackToTheComma(): void
    {
        $this->assertSame(',', (new CsvDelimiterDetector())->detect($this->csv('')));
    }

    public function testASampleEndingInsideALineIgnoresThatLine(): void
    {
        // the last line of the sample has no semicolons left, but it does not count
        $line = str_repeat('x', 1000) . ";y\n";
        $content = "a;b\n" . str_repeat($line, 20) . str_repeat('z', 70000);

        $this->assertSame(';', (new CsvDelimiterDetector())->detect($this->csv($content)));
    }

    private function inspector(): FileInspector
    {
        // QueueService and DeltaChecker are final and need the database, which reading rows does not touch
        $factory = new InterpreterFactory([
            'csv' => (new \ReflectionClass(CsvFileInterpreter::class))->newInstanceWithoutConstructor(),
            'xlsx' => (new \ReflectionClass(XlsxFileInterpreter::class))->newInstanceWithoutConstructor(),
        ]);

        return new FileInspector(new SourceFileReader($factory), $factory, new CsvDelimiterDetector());
    }

    private function csv(string $content): string
    {
        return $this->file($content, 'csv');
    }

    private function csvWithRows(int $rows): string
    {
        return $this->csv(self::CSV_HEADER . str_repeat("A-1,Chair\n", $rows));
    }

    private function file(string $content, string $extension): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('ad-hoc-inspector-', true) . '.' . $extension;
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }

    /**
     * @param array<string, array<int, array<int, mixed>>> $sheets
     */
    private function xlsx(array $sheets, string $extension = 'xlsx', bool $storeFormulaResults = true): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        foreach ($sheets as $name => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray($rows);
        }

        $path = $this->file('', $extension);
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas($storeFormulaResults);
        $writer->save($path);

        return $path;
    }

    /**
     * A sheet with only two cells: A1 and the given one.
     */
    private function sparseXlsx(string $lastCell): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');
        $sheet->setCellValue('A1', 'sku');
        $sheet->setCellValue($lastCell, 'far');

        $path = $this->file('', 'xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function xlsxWithAFormulaOnAnotherSheet(bool $storeResults): string
    {
        return $this->xlsx(
            [
                'Products' => [
                    ['sku', 'stock', 'sum', 'text', 'flag', 'code'],
                    ['A-1', '=Stock!A1', '=SUM(Stock!A1:A2)', '=Stock!A2&"x"', '=Stock!A1>1', '="00"&Stock!A1'],
                ],
                'Stock' => [[5], [7]],
            ],
            storeFormulaResults: $storeResults
        );
    }

    /**
     * A row's number is optional in a workbook.
     */
    private function removeRowNumbers(string $path): void
    {
        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = (string)$zip->getFromName(self::FIRST_SHEET);
        $zip->addFromString(self::FIRST_SHEET, (string)preg_replace('/<row r="\d+"/', '<row', $xml));
        $zip->close();
    }

    /**
     * Replaces what the writer put there, which is a definition per column it knows.
     */
    private function setColumnDefinitions(string $path, string $definitions): void
    {
        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = (string)preg_replace('~<cols>.*?</cols>~s', '', (string)$zip->getFromName(self::FIRST_SHEET));
        $xml = str_replace('<sheetData>', "<cols>$definitions</cols><sheetData>", $xml);
        $zip->addFromString(self::FIRST_SHEET, $xml);
        $zip->close();
    }

    /**
     * @param string|null $search null replaces all of the first sheet's XML
     */
    private function replaceInSheet(string $path, ?string $search, string $replacement): void
    {
        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = (string)$zip->getFromName(self::FIRST_SHEET);
        $xml = $search === null ? $replacement : str_replace($search, $replacement, $xml);
        $zip->addFromString(self::FIRST_SHEET, $xml);
        $zip->close();
    }
}
