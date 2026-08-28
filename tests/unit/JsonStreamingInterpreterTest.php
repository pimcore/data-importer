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
use JsonMachine\Exception\JsonMachineException;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\JsonFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Psr\Log\NullLogger;

/**
 * Covers the streaming code path of the JSON interpreter: simple JMESPath expressions are
 * converted to JSON pointers and the file is read record by record instead of being decoded
 * into memory at once. Complex expressions must keep falling back to the full-load path.
 */
class JsonStreamingInterpreterTest extends Unit
{
    protected $tester;

    private function createInterpreter(string $jmesPath = ''): JsonFileInterpreter
    {
        $interpreter = (new \ReflectionClass(JsonFileInterpreter::class))->newInstanceWithoutConstructor();
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName('test_json_streaming');
        $interpreter->setExecutionType(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $interpreter->setDoDeltaCheck(false);
        $interpreter->setDoCleanup(false);
        $interpreter->setDoArchiveImportFile(false);
        $interpreter->setSettings(['path' => $jmesPath]);

        return $interpreter;
    }

    private function writeJson(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'di_json_') . '.json';
        file_put_contents($path, $content);

        return $path;
    }

    private function invokeStreamingPointer(JsonFileInterpreter $interpreter): ?string
    {
        $method = new \ReflectionMethod($interpreter, 'getStreamingJsonPointer');

        return $method->invoke($interpreter);
    }

    /**
     * @return array[]
     */
    private function streamAll(JsonFileInterpreter $interpreter, string $path): array
    {
        $method = new \ReflectionMethod($interpreter, 'streamItems');

        return iterator_to_array($method->invoke($interpreter, $path), false);
    }

    public function testEmptyPathIsStreamedAsTopLevelArray(): void
    {
        $this->assertSame('', $this->invokeStreamingPointer($this->createInterpreter()));
    }

    public function testSimpleDottedPathIsConvertedToJsonPointer(): void
    {
        $this->assertSame('/data/items', $this->invokeStreamingPointer($this->createInterpreter('data.items')));
    }

    public function testComplexJmesPathExpressionsAreNotStreamed(): void
    {
        $this->assertNull($this->invokeStreamingPointer($this->createInterpreter("data.items[?name=='a']")));
        $this->assertNull($this->invokeStreamingPointer($this->createInterpreter('items[0]')));
        $this->assertNull($this->invokeStreamingPointer($this->createInterpreter('a || b')));
    }

    public function testStreamsTopLevelArrayRecords(): void
    {
        $path = $this->writeJson('[{"sku":"A","name":"One"},{"sku":"B","name":"Two"}]');

        try {
            $rows = $this->streamAll($this->createInterpreter(), $path);

            $this->assertSame([
                ['sku' => 'A', 'name' => 'One'],
                ['sku' => 'B', 'name' => 'Two'],
            ], $rows);
        } finally {
            @unlink($path);
        }
    }

    public function testStreamsNestedRecordsViaPointer(): void
    {
        $path = $this->writeJson('{"data":{"items":[{"sku":"A"},{"sku":"B"}]}}');

        try {
            $rows = $this->streamAll($this->createInterpreter('data.items'), $path);

            $this->assertSame([['sku' => 'A'], ['sku' => 'B']], $rows);
        } finally {
            @unlink($path);
        }
    }

    public function testStreamsFileWithUtf8ByteOrderMark(): void
    {
        $path = $this->writeJson("\xEF\xBB\xBF" . '[{"sku":"A"}]');

        try {
            $this->assertSame([['sku' => 'A']], $this->streamAll($this->createInterpreter(), $path));
        } finally {
            @unlink($path);
        }
    }

    public function testStreamingThrowsOnInvalidJson(): void
    {
        $path = $this->writeJson('[{"sku":"A"},{"sku":');

        try {
            $this->expectException(JsonMachineException::class);
            $this->streamAll($this->createInterpreter(), $path);
        } finally {
            @unlink($path);
        }
    }

    public function testFileValidReturnsTrueForStreamableFile(): void
    {
        $path = $this->writeJson('[{"sku":"A"}]');

        try {
            $this->assertTrue($this->createInterpreter()->fileValid($path));
        } finally {
            @unlink($path);
        }
    }

    public function testPreviewDataReadsRequestedStreamedRecord(): void
    {
        $path = $this->writeJson('{"data":{"items":[{"sku":"A"},{"sku":"B"},{"sku":"C"}]}}');

        try {
            $preview = $this->createInterpreter('data.items')->previewData($path, 1);

            $this->assertSame(['sku' => 'B'], $preview->getRawData());
        } finally {
            @unlink($path);
        }
    }

    public function testPreviewDataFallsBackToLastStreamedRecordWhenOutOfRange(): void
    {
        $path = $this->writeJson('[{"sku":"A"},{"sku":"B"}]');

        try {
            $preview = $this->createInterpreter()->previewData($path, 10);

            $this->assertSame(['sku' => 'B'], $preview->getRawData());
        } finally {
            @unlink($path);
        }
    }

    public function testComplexExpressionStillWorksViaFullLoadFallback(): void
    {
        $path = $this->writeJson('{"data":{"items":[{"sku":"A","keep":false},{"sku":"B","keep":true}]}}');

        try {
            $preview = $this->createInterpreter('data.items[?keep]')->previewData($path, 0);

            $this->assertSame('B', $preview->getRawData()['sku']);
        } finally {
            @unlink($path);
        }
    }
}
