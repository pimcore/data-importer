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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;

class SourceFileReaderTest extends Unit
{
    private const SHEET_NAME = 'Products';

    private const HEADER = ['number', 'text', 'decimal', 'flag', 'date', 'empty', 'formula'];

    private const LEADING_ZEROS = '00123';

    private const CSV = "sku,price\r\n00123,12.5\r\nB-2,\r\n";

    protected $tester;

    /**
     * @var string[]
     */
    private array $files = [];

    // unique per test, the queue is shared
    private ?string $configName = null;

    protected function _after(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        if ($this->configName !== null) {
            $this->queueService()->cleanupQueueItems($this->configName);
        }
    }

    public function testXlsxRowsAreWhatAnImportQueues(): void
    {
        $path = $this->writeXlsx();
        $config = $this->xlsxConfig(true);

        $rows = iterator_to_array($this->reader()->readRows($config, $path), false);

        $this->assertSame($this->interpretAndReadQueue($config, $path), $rows);
        $this->assertSame(
            [['123', self::LEADING_ZEROS, '12.5', 'TRUE', (string) $this->dateSerial(), null, '2']],
            $rows
        );
    }

    public function testImportStillQueuesXlsxValuesAsText(): void
    {
        $queued = $this->interpretAndReadQueue($this->xlsxConfig(true), $this->writeXlsx());

        $this->assertSame(
            [['123', self::LEADING_ZEROS, '12.5', 'TRUE', (string) $this->dateSerial(), null, '2']],
            $queued
        );
    }

    public function testXlsxTypedValues(): void
    {
        $rows = iterator_to_array($this->reader()->readRows($this->xlsxConfig(true), $this->writeXlsx(), true), false);

        $this->assertSame([[123, self::LEADING_ZEROS, 12.5, true, $this->dateSerial(), null, 2]], $rows);
    }

    public function testXlsxHeaderRowIsReadUnlessSkipped(): void
    {
        $rows = iterator_to_array($this->reader()->readRows($this->xlsxConfig(false), $this->writeXlsx(), true), false);

        $this->assertCount(2, $rows);
        $this->assertSame(self::HEADER, $rows[0]);
    }

    public function testCsvRowsAreWhatAnImportQueues(): void
    {
        $path = $this->writeFile(self::CSV, 'csv');
        $config = $this->csvConfig();

        $rows = iterator_to_array($this->reader()->readRows($config, $path), false);

        $this->assertSame($this->interpretAndReadQueue($config, $path), $rows);
        $this->assertSame([['sku' => self::LEADING_ZEROS, 'price' => '12.5'], ['sku' => 'B-2', 'price' => '']], $rows);
        $this->assertSame($rows, iterator_to_array($this->reader()->readRows($config, $path, true), false));
    }

    public function testImportStillQueuesCsvRows(): void
    {
        $queued = $this->interpretAndReadQueue($this->csvConfig(), $this->writeFile(self::CSV, 'csv'));

        $this->assertSame(
            [['sku' => self::LEADING_ZEROS, 'price' => '12.5'], ['sku' => 'B-2', 'price' => '']],
            $queued
        );
    }

    public function testCsvRowWithInvalidEncodingFailsLikeAnImport(): void
    {
        $path = $this->writeFile("sku,name\r\nA-1,M\xB2\r\n", 'csv');

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessageMatches('/Encoding error.*name/');

        iterator_to_array($this->reader()->readRows($this->csvConfig(), $path));
    }

    public function testInvalidFileIsRejected(): void
    {
        $path = $this->writeFile("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\n", 'csv');

        $this->expectException(InvalidInputException::class);

        $this->reader()->readRows($this->csvConfig(), $path);
    }

    public function testFormatWithoutRowReaderIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('File format `json` cannot read rows without importing them.');

        $this->reader()->readRows(['type' => 'json', 'settings' => []], $this->writeFile('[]', 'json'));
    }

    public function testCsvBlankLineNamesTheRow(): void
    {
        $path = $this->writeFile("sku,price\r\nA-1,1\r\n\r\nB-2,2\r\n", 'csv');

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage(sprintf('Row 3 of `%s` has 0 column(s), the header row has 2.', basename($path)));

        iterator_to_array($this->readerWithoutDatabase()->readRows($this->csvConfig(), $path));
    }

    public function testCsvRaggedRowNamesTheRow(): void
    {
        $path = $this->writeFile("sku,price\r\nA-1,1,extra\r\n", 'csv');

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage(sprintf('Row 2 of `%s` has 3 column(s), the header row has 2.', basename($path)));

        iterator_to_array($this->readerWithoutDatabase()->readRows($this->csvConfig(), $path));
    }

    public function testImportStillFailsOnARaggedCsvRowAsBefore(): void
    {
        $interpreter = $this->interpreterWithoutDatabase(CsvFileInterpreter::class);
        $interpreter->setSettings($this->csvConfig()['settings']);

        $this->expectException(\ValueError::class);

        $interpreter->interpretFile($this->writeFile("sku,price\r\nA-1,1,extra\r\n", 'csv'));
    }

    public function testInvalidEncodingNamesTheRow(): void
    {
        $path = $this->writeFile("sku,name\r\nA-1,ok\r\nA-2,M\xB2\r\n", 'csv');

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage(sprintf('Encoding error in row 3 of `%s`: invalid UTF-8', basename($path)));

        iterator_to_array($this->readerWithoutDatabase()->readRows($this->csvConfig(), $path));
    }

    public function testMissingXlsxSheetIsRejected(): void
    {
        $path = $this->writeXlsx();
        $config = ['type' => 'xlsx', 'settings' => ['skipFirstRow' => true, 'sheetName' => 'Missing']];

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage(sprintf('Sheet `Missing` not found in `%s`.', basename($path)));

        $this->readerWithoutDatabase()->readRows($config, $path);
    }

    public function testUnreadableXlsxIsRejected(): void
    {
        $path = $this->writeFile("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\n", 'xlsx');

        $this->expectException(InvalidInputException::class);

        $this->readerWithoutDatabase()->readRows($this->xlsxConfig(true), $path);
    }

    public function testCsvHeaderWithInvalidEncodingNamesTheColumn(): void
    {
        $path = $this->writeFile("sku,M\xB2\r\nA-1,ok\r\n", 'csv');

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage(
            sprintf('Encoding error in row 2 of `%s`: invalid UTF-8 characters in column(s) #1 (header).', basename($path))
        );

        iterator_to_array($this->readerWithoutDatabase()->readRows($this->csvConfig(), $path));
    }

    public function testCsvGoneBeforeTheRowsAreReadIsRejected(): void
    {
        $path = $this->writeFile(self::CSV, 'csv');
        $rows = $this->readerWithoutDatabase()->readRows($this->csvConfig(), $path);
        unlink($path);

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage(sprintf('File `%s` cannot be read.', basename($path)));

        iterator_to_array($rows);
    }

    private function reader(): SourceFileReader
    {
        return $this->tester->grabService(SourceFileReader::class);
    }

    /**
     * Reading rows touches neither the queue nor the delta checker, which need the database.
     */
    private function readerWithoutDatabase(): SourceFileReader
    {
        return new SourceFileReader(new InterpreterFactory([
            'csv' => $this->interpreterWithoutDatabase(CsvFileInterpreter::class),
            'xlsx' => $this->interpreterWithoutDatabase(XlsxFileInterpreter::class),
        ]));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function interpreterWithoutDatabase(string $class): object
    {
        // QueueService and DeltaChecker are final and cannot be doubled
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    private function queueService(): QueueService
    {
        return $this->tester->grabService(QueueService::class);
    }

    /**
     * @param array<string, mixed> $interpreterConfig
     *
     * @return list<mixed>
     */
    private function interpretAndReadQueue(array $interpreterConfig, string $path): array
    {
        $this->configName = uniqid('source-file-reader-test-');

        /** @var InterpreterFactory $factory */
        $factory = $this->tester->grabService(InterpreterFactory::class);
        $interpreter = $factory->loadInterpreter($this->configName, $interpreterConfig, [
            'executionType' => ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
        ]);
        $this->assertTrue($interpreter->interpretFile($path));

        $queued = [];
        $queueService = $this->queueService();
        foreach ($queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL) as $id) {
            $entry = $queueService->getQueueEntryById((int) $id);
            if ($entry['configName'] === $this->configName) {
                $queued[] = json_decode($entry['data'], true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return $queued;
    }

    /**
     * @return array<string, mixed>
     */
    private function xlsxConfig(bool $skipFirstRow): array
    {
        return ['type' => 'xlsx', 'settings' => ['skipFirstRow' => $skipFirstRow, 'sheetName' => self::SHEET_NAME]];
    }

    /**
     * @return array<string, mixed>
     */
    private function csvConfig(): array
    {
        return [
            'type' => 'csv',
            'settings' => [
                'skipFirstRow' => true,
                'saveHeaderName' => true,
                'delimiter' => ',',
                'enclosure' => '"',
                'escape' => '\\',
            ],
        ];
    }

    private function dateSerial(): int
    {
        return (int) Date::PHPToExcel(new \DateTimeImmutable('2026-03-01'));
    }

    private function writeXlsx(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);
        $sheet->fromArray([self::HEADER]);
        $sheet->setCellValue('A2', 123);
        $sheet->setCellValueExplicit('B2', self::LEADING_ZEROS, DataType::TYPE_STRING);
        $sheet->setCellValue('C2', 12.5);
        $sheet->setCellValue('D2', true);
        $sheet->setCellValue('E2', $this->dateSerial());
        $sheet->getStyle('E2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->setCellValue('G2', '=1+1');

        $path = $this->writeFile('', 'xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function writeFile(string $content, string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'di_rows_');
        $path = $base . '.' . $extension;
        file_put_contents($path, $content);
        array_push($this->files, $base, $path);

        return $path;
    }
}
