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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\ChunkedRowsReadFilter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Psr\Log\NullLogger;

/**
 * Covers the chunked XLSX import reading that replaced the single toArray() call
 * (large files would otherwise exhaust the memory limit): the read filter must
 * restrict loading to its row range and chunk-wise reading must reproduce the
 * same rows a full load produced.
 */
class XlsxChunkedReadTest extends Unit
{
    protected $tester;

    private function createInterpreter(bool $skipFirstRow = false): XlsxFileInterpreter
    {
        // the constructor collaborators are final and cannot be doubled; none of the code
        // paths exercised here touch them
        $interpreter = (new \ReflectionClass(XlsxFileInterpreter::class))->newInstanceWithoutConstructor(); // NOSONAR
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName('test_xlsx_chunked');
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

        $path = tempnam(sys_get_temp_dir(), 'di_xlsx_') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public function testChunkedRowsReadFilterOnlyAcceptsRowsInRange(): void
    {
        $filter = new ChunkedRowsReadFilter(2, 4);

        $this->assertFalse($filter->readCell('A', 1));
        $this->assertTrue($filter->readCell('A', 2));
        $this->assertTrue($filter->readCell('B', 3));
        $this->assertTrue($filter->readCell('A', 4));
        $this->assertFalse($filter->readCell('A', 5));
    }

    public function testChunkedReadingReproducesAllRows(): void
    {
        $rows = [
            ['sku', 'name'],
            ['A', 'One'],
            ['B', 'Two'],
            ['C', 'Three'],
            ['D', 'Four'],
        ];
        $path = $this->writeXlsx($rows);
        $interpreter = $this->createInterpreter(true);

        try {
            $worksheetInfo = (new \ReflectionMethod($interpreter, 'getWorksheetInfo'))->invoke($interpreter, $path);
            $this->assertSame(5, $worksheetInfo['totalRows']);
            $this->assertSame('B', $worksheetInfo['lastColumnLetter']);

            // read in chunks of 2 rows, starting behind the header row
            $readRow = new \ReflectionMethod($interpreter, 'readRow');
            $collected = [];
            $chunkSize = 2;

            for ($chunkStart = 2; $chunkStart <= $worksheetInfo['totalRows']; $chunkStart += $chunkSize) {
                $chunkEnd = min($chunkStart + $chunkSize - 1, $worksheetInfo['totalRows']);

                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly('Sheet1');
                $reader->setReadFilter(new ChunkedRowsReadFilter($chunkStart, $chunkEnd));
                $spreadSheet = $reader->load($path);
                $spreadSheet->setActiveSheetIndexByName('Sheet1');
                $sheet = $spreadSheet->getActiveSheet();

                $lastColumnLetter = $worksheetInfo['lastColumnLetter'];
                for ($rowNumber = $chunkStart; $rowNumber <= $chunkEnd; $rowNumber++) {
                    $collected[] = $readRow->invoke($interpreter, $sheet, $rowNumber, $lastColumnLetter);
                }

                $spreadSheet->disconnectWorksheets();
            }

            $this->assertSame(array_slice($rows, 1), $collected);
        } finally {
            @unlink($path);
        }
    }
}
