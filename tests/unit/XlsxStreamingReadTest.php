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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Psr\Log\NullLogger;

/**
 * Covers the streaming XLSX import path used for large workbooks: rows are read in a
 * single pass with bounded memory, formula cells resolve to the calculation result
 * cached in the file, rows are padded to the sheet width so column indexes stay stable
 * like in the full-load code path, and cell values are formatted exactly like the
 * full-load toArray() output so delta checks do not depend on the file size.
 */
class XlsxStreamingReadTest extends Unit
{
    protected $tester;

    private function createInterpreter(bool $skipFirstRow = false): XlsxFileInterpreter
    {
        // the constructor collaborators are final and cannot be doubled; none of the code
        // paths exercised here touch them
        $interpreter = (new \ReflectionClass(XlsxFileInterpreter::class))->newInstanceWithoutConstructor(); // NOSONAR
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName('test_xlsx_streaming');
        $interpreter->setExecutionType(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $interpreter->setDoDeltaCheck(false);
        $interpreter->setDoCleanup(false);
        $interpreter->setDoArchiveImportFile(false);
        $interpreter->setSettings(['skipFirstRow' => $skipFirstRow, 'sheetName' => 'Sheet1']);

        return $interpreter;
    }

    private function writeXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');
        $sheet->fromArray($rows);

        return $this->saveSpreadsheet($spreadsheet);
    }

    private function writeMixedTypeXlsx(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');

        $sheet->setCellValue('A1', 42);
        $sheet->setCellValue('B1', 1.5);
        $sheet->setCellValue('C1', true);
        $sheet->setCellValue('D1', 45366);
        $sheet->getStyle('D1')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);
        $sheet->setCellValue('E1', 45366.75);
        $sheet->getStyle('E1')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DATETIME);
        $sheet->setCellValue('F1', 0.5);
        $sheet->getStyle('F1')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_TIME4);
        $sheet->setCellValue('G1', 1.25);
        $sheet->getStyle('G1')->getNumberFormat()->setFormatCode('[h]:mm:ss');
        $sheet->setCellValue('H1', '=A1+B1');
        $sheet->setCellValue('I1', 'text');
        $sheet->setCellValueExplicit('J1', '0123', DataType::TYPE_STRING);
        $sheet->setCellValue('L1', 'after gap');

        return $this->saveSpreadsheet($spreadsheet);
    }

    private function saveSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'di_xlsx_') . '.xlsx';
        // the writer pre-calculates formulas, so the file carries cached formula results
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * @return array[]
     */
    private function streamAll(XlsxFileInterpreter $interpreter, string $path, int $totalColumns): array
    {
        $method = new \ReflectionMethod($interpreter, 'streamSheetRows');

        return iterator_to_array($method->invoke($interpreter, $path, $totalColumns), false);
    }

    /**
     * @return array[]
     */
    private function loadAll(XlsxFileInterpreter $interpreter, string $path): array
    {
        $method = new \ReflectionMethod($interpreter, 'loadSheetRows');

        return $method->invoke($interpreter, $path);
    }

    public function testWorksheetInfoContainsDimensions(): void
    {
        $path = $this->writeXlsx([['sku', 'name'], ['A', 'One']]);
        $interpreter = $this->createInterpreter();

        try {
            $info = (new \ReflectionMethod($interpreter, 'getWorksheetInfo'))->invoke($interpreter, $path);

            $this->assertSame(2, $info['totalRows']);
            $this->assertSame(2, $info['totalColumns']);
            $this->assertSame('B', $info['lastColumnLetter']);
        } finally {
            @unlink($path);
        }
    }

    public function testStreamsAllRowsInOrder(): void
    {
        $rows = [
            ['sku', 'name'],
            ['A', 'One'],
            ['B', 'Two'],
            ['C', 'Three'],
        ];
        $path = $this->writeXlsx($rows);

        try {
            $streamed = $this->streamAll($this->createInterpreter(true), $path, 2);

            $this->assertSame(array_slice($rows, 1), $streamed);
        } finally {
            @unlink($path);
        }
    }

    public function testStreamedRowsArePaddedToSheetWidth(): void
    {
        $path = $this->writeXlsx([
            ['sku', 'name', 'color'],
            ['A'],
        ]);

        try {
            $streamed = $this->streamAll($this->createInterpreter(true), $path, 3);

            $this->assertCount(1, $streamed);
            $this->assertSame(['A', null, null], $streamed[0]);
        } finally {
            @unlink($path);
        }
    }

    public function testFormulaCellsResolveToCachedResult(): void
    {
        $path = $this->writeXlsx([
            [2, 3, '=A1+B1'],
        ]);

        try {
            $streamed = $this->streamAll($this->createInterpreter(), $path, 3);

            $this->assertCount(1, $streamed);
            $this->assertSame('5', $streamed[0][2]);
        } finally {
            @unlink($path);
        }
    }

    public function testStreamedValuesMatchFullLoadPath(): void
    {
        $path = $this->writeMixedTypeXlsx();
        $interpreter = $this->createInterpreter();

        try {
            $fullLoad = $this->loadAll($interpreter, $path);
            $streamed = $this->streamAll($interpreter, $path, 12);

            $this->assertSame($fullLoad, $streamed);
            $this->assertSame(
                ['42', '1.5', 'TRUE', '45366', '45366.75', '0.5', '1.25', '43.5', 'text', '0123', null, 'after gap'],
                $streamed[0]
            );
        } finally {
            @unlink($path);
        }
    }
}
