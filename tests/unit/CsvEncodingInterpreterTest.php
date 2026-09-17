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
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Psr\Log\NullLogger;

/**
 * Regression coverage for https://github.com/pimcore/data-importer/issues/466
 *
 * A CSV row whose only difference from a working file is a single non-UTF-8 byte
 * (0xB2 - the "superscript two" character in ISO-8859-1) must not be silently dropped
 * on import nor produce a confusing generic error on preview. The importer should fail
 * loud with a clear encoding message.
 */
class CsvEncodingInterpreterTest extends Unit
{
    protected $tester;

    // Working row: ASCII '2' at the end of the first column.
    private const VALID_CSV = "desc,foo\r\nMAT/8.2SF/0.8M2,1\r\n";

    // Identical row but with byte 0xB2 ('superscript two' in ISO-8859-1) instead of '2',
    // which is an invalid standalone UTF-8 byte.
    private const INVALID_CSV = "desc,foo\r\nMAT/8.2SF/0.8M\xB2,1\r\n";

    /**
     * QueueService and DeltaChecker are declared final and cannot be doubled. The code paths
     * exercised here either do not touch them (preview) or throw before reaching them (the
     * encoding check runs first in processImportRow), so the interpreter is built without its
     * constructor dependencies.
     */
    private function createInterpreter(): CsvFileInterpreter
    {
        $interpreter = (new \ReflectionClass(CsvFileInterpreter::class))->newInstanceWithoutConstructor();
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

    public function testPreviewReturnsDataForValidCsv(): void
    {
        $path = $this->writeCsv(self::VALID_CSV);

        try {
            $preview = $this->createInterpreter()->previewData($path);
            $this->assertSame('MAT/8.2SF/0.8M2', $preview->getRawData()['desc']);
        } finally {
            @unlink($path);
        }
    }

    public function testPreviewThrowsClearEncodingErrorOnInvalidUtf8(): void
    {
        $path = $this->writeCsv(self::INVALID_CSV);

        try {
            $this->expectException(InvalidInputException::class);
            $this->expectExceptionMessageMatches('/Encoding error.*desc/');
            $this->createInterpreter()->previewData($path);
        } finally {
            @unlink($path);
        }
    }

    public function testInterpretFileThrowsClearEncodingErrorOnInvalidUtf8(): void
    {
        $path = $this->writeCsv(self::INVALID_CSV);

        try {
            $this->expectException(InvalidInputException::class);
            $this->expectExceptionMessageMatches('/Encoding error.*desc/');
            $this->createInterpreter()->interpretFile($path);
        } finally {
            @unlink($path);
        }
    }
}
