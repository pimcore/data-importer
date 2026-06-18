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
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Psr\Log\NullLogger;

/**
 * Regression coverage for https://github.com/pimcore/data-importer/issues/466
 *
 * A CSV row whose only difference from a working file is a single non-UTF-8 byte
 * (0xB2 - the "superscript two" character in ISO-8859-1) must not be silently dropped
 * on import nor produce a generic, confusing error on preview. The importer should fail
 * loud with a clear encoding message.
 */
class CsvEncodingInterpreterTest extends Unit
{
    protected $tester;

    private const HEADER = "desc,foo\r\n";

    // Working row, ASCII '2' at the end of the first column.
    private const VALID_ROW = "MAT/8.2SF/0.8M2,1\r\n";

    // Identical row but with byte 0xB2 instead of '2' -> invalid standalone UTF-8 byte.
    private const INVALID_ROW = "MAT/8.2SF/0.8M\xB2,1\r\n";

    private function createInterpreter(QueueService $queueService): CsvFileInterpreter
    {
        $interpreter = new CsvFileInterpreter(
            $this->createMock(DeltaChecker::class),
            $queueService,
            $this->createMock(ApplicationLogger::class),
        );
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName('test_encoding');
        $interpreter->setExecutionType(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $interpreter->setDoDeltaCheck(false);
        $interpreter->setDoCleanup(false);
        $interpreter->setDoArchiveImportFile(false);
        $interpreter->setSettings([
            'skipFirstRow' => true,
            'saveHeaderName' => true,
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
        ]);

        return $interpreter;
    }

    private function writeCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'di_csv_') . '.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function testValidRowIsQueued(): void
    {
        $queueService = $this->createMock(QueueService::class);
        $queueService->expects(self::once())
            ->method('addItemToQueue')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::callback(static fn (string $data): bool => json_decode($data, true) !== null),
            );

        $interpreter = $this->createInterpreter($queueService);

        $process = (new \ReflectionObject($interpreter))->getMethod('processImportRow');
        $process->setAccessible(true);
        $process->invoke($interpreter, ['desc' => 'MAT/8.2SF/0.8M2', 'foo' => '1']);
    }

    public function testRowWithInvalidEncodingThrows(): void
    {
        $queueService = $this->createMock(QueueService::class);
        $queueService->expects(self::never())->method('addItemToQueue');

        $interpreter = $this->createInterpreter($queueService);

        $process = (new \ReflectionObject($interpreter))->getMethod('processImportRow');
        $process->setAccessible(true);

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessageMatches('/Encoding error.*desc/');
        $process->invoke($interpreter, ['desc' => "MAT/8.2SF/0.8M\xB2", 'foo' => '1']);
    }

    public function testInterpretFileThrowsOnInvalidEncoding(): void
    {
        $queueService = $this->createMock(QueueService::class);
        $queueService->expects(self::never())->method('addItemToQueue');

        $interpreter = $this->createInterpreter($queueService);
        $path = $this->writeCsv(self::HEADER . self::INVALID_ROW);

        try {
            $this->expectException(InvalidInputException::class);
            $interpreter->interpretFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function testPreviewReturnsDataForValidCsv(): void
    {
        $interpreter = $this->createInterpreter($this->createMock(QueueService::class));
        $path = $this->writeCsv(self::HEADER . self::VALID_ROW);

        try {
            $preview = $interpreter->previewData($path);
            self::assertSame('MAT/8.2SF/0.8M2', $preview->getRawData()['desc']);
        } finally {
            @unlink($path);
        }
    }

    public function testPreviewThrowsOnInvalidEncoding(): void
    {
        $interpreter = $this->createInterpreter($this->createMock(QueueService::class));
        $path = $this->writeCsv(self::HEADER . self::INVALID_ROW);

        try {
            $this->expectException(InvalidInputException::class);
            $interpreter->previewData($path);
        } finally {
            @unlink($path);
        }
    }
}
