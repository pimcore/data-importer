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
use Pimcore\Bundle\DataImporterBundle\Event\PreInterpretFileEvent;
use Pimcore\Bundle\DataImporterBundle\Event\PreQueueRowEvent;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * End-to-end coverage for the interpretation-stage events: rows extracted by a stock
 * interpreter can be modified, skipped, or fanned out by PreQueueRowEvent listeners, and
 * PreInterpretFileEvent listeners can replace the source file - without a custom interpreter.
 */
class InterpreterRowEventTest extends Unit
{
    protected $tester;

    private const CSV = "sku,name\nA-1,First\nB-2,Second\n";

    private string $configName;

    /**
     * @var string[]
     */
    private array $tempFiles = [];

    protected function _before(): void
    {
        $this->configName = uniqid('test_row_event_');
    }

    protected function _after(): void
    {
        $this->drainQueue();

        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
    }

    private function queueService(): QueueService
    {
        return new QueueService();
    }

    private function createInterpreter(?EventDispatcherInterface $eventDispatcher): CsvFileInterpreter
    {
        $interpreter = new CsvFileInterpreter(
            new DeltaChecker(\Pimcore\Db::get()),
            $this->queueService(),
            ApplicationLogger::getInstance()
        );
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName($this->configName);
        $interpreter->setExecutionType(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $interpreter->setIdDataIndex('sku');
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
        $interpreter->setEventDispatcher($eventDispatcher);

        return $interpreter;
    }

    private function writeCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'di_csv_') . '.csv';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * @return array<int, array>
     */
    private function loadQueuedRows(): array
    {
        $queueService = $this->queueService();
        $rows = [];
        foreach ($queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL) as $id) {
            $entry = $queueService->getQueueEntryById($id);
            if (($entry['configName'] ?? null) === $this->configName) {
                $rows[] = json_decode($entry['data'], true);
            }
        }

        return $rows;
    }

    private function drainQueue(): void
    {
        $queueService = $this->queueService();
        foreach ($queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL) as $id) {
            $entry = $queueService->getQueueEntryById($id);
            if (($entry['configName'] ?? null) === $this->configName) {
                $queueService->markQueueEntryAsProcessed($id);
            }
        }
    }

    public function testCompilerPassWiresEventDispatcherIntoTaggedInterpreters(): void
    {
        try {
            $interpreter = $this->tester->grabService(CsvFileInterpreter::class);
        } catch (\Throwable $e) {
            // The service is private; retrieving it by id needs a test container
            // (framework.test: true), which not every local project setup provides.
            $this->markTestSkipped('Service container does not expose private services: ' . $e->getMessage());
        }

        $property = new \ReflectionProperty($interpreter, 'eventDispatcher');
        $this->assertInstanceOf(EventDispatcherInterface::class, $property->getValue($interpreter));
    }

    public function testRowsAreQueuedUnchangedWithoutListeners(): void
    {
        $interpreter = $this->createInterpreter(new EventDispatcher());

        $this->assertTrue($interpreter->interpretFile($this->writeCsv(self::CSV)));

        $rows = $this->loadQueuedRows();
        $this->assertCount(2, $rows);
        $this->assertSame(['sku' => 'A-1', 'name' => 'First'], $rows[0]);
        $this->assertSame(['sku' => 'B-2', 'name' => 'Second'], $rows[1]);
    }

    public function testRowsAreQueuedUnchangedWithoutDispatcher(): void
    {
        $interpreter = $this->createInterpreter(null);

        $this->assertTrue($interpreter->interpretFile($this->writeCsv(self::CSV)));

        $this->assertCount(2, $this->loadQueuedRows());
    }

    public function testListenerCanModifyRows(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PreQueueRowEvent::class, function (PreQueueRowEvent $event): void {
            $row = $event->getOriginalRow();
            $row['path'] = '/products/' . $row['sku'];
            $event->setRows([$row]);
        });

        $this->createInterpreter($dispatcher)->interpretFile($this->writeCsv(self::CSV));

        $rows = $this->loadQueuedRows();
        $this->assertCount(2, $rows);
        $this->assertSame('/products/A-1', $rows[0]['path']);
        $this->assertSame('/products/B-2', $rows[1]['path']);
    }

    public function testListenerCanSkipRows(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PreQueueRowEvent::class, function (PreQueueRowEvent $event): void {
            if ($event->getOriginalRow()['sku'] === 'B-2') {
                $event->skipRow();
            }
        });

        $this->createInterpreter($dispatcher)->interpretFile($this->writeCsv(self::CSV));

        $rows = $this->loadQueuedRows();
        $this->assertCount(1, $rows);
        $this->assertSame('A-1', $rows[0]['sku']);
    }

    public function testListenerCanFanOutOneRowIntoMultipleQueueItems(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PreQueueRowEvent::class, function (PreQueueRowEvent $event): void {
            $row = $event->getOriginalRow();
            if ($row['sku'] !== 'A-1') {
                return;
            }

            $rows = [];
            foreach ([2023, 2024, 2025] as $year) {
                $rows[] = $row + ['year' => $year, 'key' => $row['sku'] . '-' . $year];
            }
            $event->setRows($rows);
        });

        $this->createInterpreter($dispatcher)->interpretFile($this->writeCsv(self::CSV));

        $rows = $this->loadQueuedRows();
        $this->assertCount(4, $rows, 'A-1 fans out into three rows, B-2 stays one row');
        $this->assertSame(['A-1-2023', 'A-1-2024', 'A-1-2025'], array_column(array_slice($rows, 0, 3), 'key'));
        $this->assertSame('B-2', $rows[3]['sku']);
    }

    public function testPreInterpretFileEventCanReplaceTheSourceFile(): void
    {
        $originalPath = $this->writeCsv(self::CSV);
        $replacementPath = $this->writeCsv("sku,name\nC-3,Replaced\n");

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            PreInterpretFileEvent::class,
            function (PreInterpretFileEvent $event) use ($replacementPath): void {
                $event->setPath($replacementPath);
            }
        );

        $this->createInterpreter($dispatcher)->interpretFile($originalPath);

        $rows = $this->loadQueuedRows();
        $this->assertCount(1, $rows);
        $this->assertSame('C-3', $rows[0]['sku']);
    }
}
