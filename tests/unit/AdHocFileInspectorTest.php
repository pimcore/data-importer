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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\CsvDelimiterDetector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\RefusalReason;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;

class AdHocFileInspectorTest extends Unit
{
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
        $path = $this->csv("Artikelnr;Bezeichnung;Preis\nNH-1001;Trail jacket;\"189,90\"\nNH-1002;Rain pants;\"99,00\"\n");

        $inspection = $this->inspector()->inspect($path, FileType::Csv, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(';', $inspection->detectedDelimiter);
        $this->assertSame(';', $inspection->settings['delimiter']);
        $this->assertSame('"', $inspection->settings['enclosure']);
        $this->assertSame('\\', $inspection->settings['escape']);
        $this->assertSame(2, $inspection->rowCount);
        $this->assertSame(
            [
                ['dataIndex' => 'Artikelnr', 'label' => 'Artikelnr'],
                ['dataIndex' => 'Bezeichnung', 'label' => 'Bezeichnung'],
                ['dataIndex' => 'Preis', 'label' => 'Preis'],
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
        $path = $this->csv("sku,name\n" . implode("\n", array_map(static fn (int $i): string => "A-$i,Name $i", range(1, 7))) . "\n");

        $inspection = $this->inspector()->inspect($path, FileType::Csv, new SettingsInput());

        $this->assertSame(7, $inspection->rowCount);
        $this->assertSame([['A-1', 'Name 1'], ['A-2', 'Name 2'], ['A-3', 'Name 3']], $inspection->firstRows);
    }

    public function testTheByteOrderMarkIsNoPartOfTheFirstColumn(): void
    {
        $inspection = $this->inspector()->inspect($this->csv("\xEF\xBB\xBFsku,name\nA-1,Chair\n"), FileType::Csv, new SettingsInput());

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

    public function testAHeaderOnlyCsvHasItsColumnsAndNoRows(): void
    {
        $inspection = $this->inspector()->inspect($this->csv("sku,name\n"), FileType::Csv, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(0, $inspection->rowCount);
        $this->assertCount(2, $inspection->columns);
    }

    public function testACsvUpToTheLimitIsTakenAndOneRowMoreIsRefusedWithItsCount(): void
    {
        $atLimit = $this->inspector()->inspect($this->csvWithRows(FileInspector::MAX_ROWS), FileType::Csv, new SettingsInput());
        $this->assertNull($atLimit->refusal);
        $this->assertSame(FileInspector::MAX_ROWS, $atLimit->rowCount);

        $over = $this->inspector()->inspect($this->csvWithRows(FileInspector::MAX_ROWS + 1), FileType::Csv, new SettingsInput());
        $this->assertSame(RefusalReason::TooManyRows, $over->refusal);
        $this->assertSame(FileInspector::MAX_ROWS + 1, $over->rowCount);
        $this->assertSame([], $over->columns);
    }

    public function testACsvRowNotFittingTheHeaderIsUnreadableButKeepsItsSettings(): void
    {
        $inspection = $this->inspector()->inspect($this->csv("sku,name\nA-1,Chair\nA-2\n"), FileType::Csv, new SettingsInput());

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
        $this->assertSame(',', $inspection->settings['delimiter']);
    }

    public function testACsvNotInUtf8IsUnreadable(): void
    {
        $inspection = $this->inspector()->inspect($this->csv("sku,name\nA-1,gr\xFCn\n"), FileType::Csv, new SettingsInput());

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testTwoColumnsOfOneNameAreUnreadableBecauseAnImportWouldKeepOnlyOne(): void
    {
        $inspection = $this->inspector()->inspect($this->csv("sku,name,name\nA-1,Chair,Table\n"), FileType::Csv, new SettingsInput());

        $this->assertSame(RefusalReason::Unreadable, $inspection->refusal);
    }

    public function testUnnamedCsvColumnsAreLeftOut(): void
    {
        $inspection = $this->inspector()->inspect($this->csv("sku,name,,\nA-1,Chair,,\n"), FileType::Csv, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(['sku', 'name'], array_column($inspection->columns, 'dataIndex'));
        $this->assertSame([['A-1', 'Chair']], $inspection->firstRows);
    }

    public function testAnEnclosureOfSeveralBytesIsUnreadableInsteadOfAnError(): void
    {
        $inspection = $this->inspector()->inspect(
            $this->csv("sku,name\nA-1,Chair\n"),
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

        $withoutHeader = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput(hasHeaderRow: false, sheetName: 'Notes'));
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
        $path = $this->file("sku,name\nA-1,Chair\n", 'xlsx');

        $this->assertFalse($this->inspector()->isReadable($path, FileType::Xlsx));
        $this->assertSame(RefusalReason::Unreadable, $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput())->refusal);
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

        $this->assertSame(RefusalReason::Unreadable, $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput())->refusal);
    }

    public function testAWorkbookWithAPartOnlyTheLoadReadsBrokenIsUnreadableInsteadOfAnError(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $zip = new \ZipArchive();
        $zip->open($path);
        $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t');
        $zip->close();

        $this->assertSame(RefusalReason::Unreadable, $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput())->refusal);
    }

    public function testAnXlsxUpToTheLimitIsTakenWithItsCount(): void
    {
        $path = $this->xlsx(['Products' => array_fill(0, FileInspector::MAX_ROWS + 1, ['value'])]);

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(FileInspector::MAX_ROWS, $inspection->rowCount);
    }

    public function testARowThatOnlyCarriesFormattingDoesNotCountForTheLimit(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1'], ['A-2']]]);
        $this->replaceInSheet($path, '</sheetData>', '<row r="6000" s="1" customFormat="1"/></sheetData>');

        $inspection = $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput());

        $this->assertNull($inspection->refusal);
        $this->assertSame(2, $inspection->rowCount);
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

        $this->assertSame(RefusalReason::Unreadable, $this->inspector()->inspect($path, FileType::Xlsx, new SettingsInput())->refusal);
    }

    public function testAnXlsxIsReadable(): void
    {
        $this->assertTrue($this->inspector()->isReadable($this->xlsx(['Products' => [['a']]]), FileType::Xlsx));
    }

    public function testACsvIsReadableAndABinaryFileNamedCsvIsNot(): void
    {
        $this->assertTrue($this->inspector()->isReadable($this->csv("sku,name\nA-1,Chair\n"), FileType::Csv));
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
        return $this->csv("sku,name\n" . str_repeat("A-1,Chair\n", $rows));
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
     * @param string|null $search null replaces all of the first sheet's XML
     */
    private function replaceInSheet(string $path, ?string $search, string $replacement): void
    {
        $part = 'xl/worksheets/sheet1.xml';
        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = $search === null ? $replacement : str_replace($search, $replacement, (string)$zip->getFromName($part));
        $zip->addFromString($part, $xml);
        $zip->close();
    }
}
