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
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;

/**
 * The XLSX preview shares its cell-value rule with the import path. These tests pin the
 * preview output - columns, the addressed record and the out-of-range fallback - so the
 * shared rule cannot change the preview while it serves the import.
 */
class XlsxFileInterpreterPreviewTest extends Unit
{
    protected $tester;

    private const SHEET_NAME = 'Sheet1';

    private const COLUMNS = ['sku [0]', 'name [1]', 'price [2]', 'released [3]', 'note [4]'];

    private const LAST_DATA_ROW = ['B-2', null, 1234.5, null, null];

    /**
     * @var string[]
     */
    private array $tempFiles = [];

    protected function _after(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
    }

    /**
     * The preview touches neither the queue nor the delta check, and QueueService and
     * DeltaChecker are final and cannot be doubled, so the interpreter is built without
     * its constructor dependencies.
     */
    private function createInterpreter(): XlsxFileInterpreter
    {
        $interpreter = (new \ReflectionClass(XlsxFileInterpreter::class))->newInstanceWithoutConstructor();
        $interpreter->setSettings(['skipFirstRow' => true, 'sheetName' => self::SHEET_NAME]);

        return $interpreter;
    }

    private function writeFixture(): string
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
        $sheet->setCellValue('A4', 'B-2');
        $sheet->setCellValue('C4', 1234.5);

        $path = tempnam(sys_get_temp_dir(), 'di_xlsx_') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function testFirstRecordCarriesTheHeaderColumns(): void
    {
        $preview = $this->createInterpreter()->previewData($this->writeFixture());

        $this->assertSame(self::COLUMNS, array_column($preview->getDataColumnHeaders(), 'label'));
        $this->assertSame(['A-1', 'First', 9.99, 45356.0, 'plain'], $preview->getRawData());
        $this->assertSame(0, $preview->getRecordNumber());
    }

    public function testRecordInTheMiddleOfTheSheet(): void
    {
        $preview = $this->createInterpreter()->previewData($this->writeFixture(), 2);

        $this->assertSame(self::LAST_DATA_ROW, $preview->getRawData());
        $this->assertSame(2, $preview->getRecordNumber());
    }

    public function testOutOfRangeRecordFallsBackToTheLastDataRow(): void
    {
        $preview = $this->createInterpreter()->previewData($this->writeFixture(), 10);

        $this->assertSame(self::LAST_DATA_ROW, $preview->getRawData());
        $this->assertSame(2, $preview->getRecordNumber());
    }
}
