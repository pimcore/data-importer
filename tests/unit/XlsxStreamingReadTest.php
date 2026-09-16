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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Psr\Log\NullLogger;

/**
 * Covers the streaming XLSX import path used for large workbooks: rows are read in a
 * single pass with bounded memory, formula cells resolve to the calculation result
 * cached in the file, and rows are padded to the sheet width so column indexes stay
 * stable like in the full-load code path.
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
            $this->assertEquals(5, $streamed[0][2]);
        } finally {
            @unlink($path);
        }
    }
}
