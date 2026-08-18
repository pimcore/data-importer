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
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Model\Element\ElementInterface;
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

    private function createInterpreter(
        ?EventDispatcherInterface $eventDispatcher,
        bool $doCleanup = false
    ): CsvFileInterpreter {
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
        $interpreter->setDoCleanup($doCleanup);
        if ($doCleanup) {
            $interpreter->setResolver($this->createResolver());
        }
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
     * A resolver whose loading strategy pretends the elements A-1 and B-2 already exist,
     * so cleanup behavior can be observed through the cleanup queue items alone.
     */
    private function createResolver(): Resolver
    {
        $resolver = new Resolver();
        $resolver->setLoadingStrategy(new class () implements LoadStrategyInterface {
            public function loadElement(array $inputData): ?ElementInterface
            {
                return null;
            }

            public function loadElementByIdentifier($identifier): ?ElementInterface
            {
                return null;
            }

            public function extractIdentifierFromData(array $inputData)
            {
                return $inputData['sku'] ?? null;
            }

            public function loadFullIdentifierList(): array
            {
                return ['A-1', 'B-2'];
            }

            public function setDataObjectClassId($dataObjectClassId): void
            {
            }

            public function setSettings(array $settings): void
            {
            }
        });

        return $resolver;
    }

    /**
     * @return array<int, array>
     */
    private function loadQueueEntries(string $jobType): array
    {
        $queueService = $this->queueService();
        $entries = [];
        foreach ($queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL) as $id) {
            $entry = $queueService->getQueueEntryById($id);
            if (($entry['configName'] ?? null) === $this->configName && ($entry['jobType'] ?? null) === $jobType) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array>
     */
    private function loadQueuedRows(): array
    {
        return array_map(
            static fn (array $entry): array => json_decode($entry['data'], true),
            $this->loadQueueEntries(ImportProcessingService::JOB_TYPE_PROCESS)
        );
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

    public function testSkippedRowWithKeptIdentifierSurvivesCleanup(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PreQueueRowEvent::class, function (PreQueueRowEvent $event): void {
            if ($event->getOriginalRow()['sku'] === 'B-2') {
                $event->skipRow(keepInCleanupIdentifierCache: true);
            }
        });

        $this->createInterpreter($dispatcher, doCleanup: true)->interpretFile($this->writeCsv(self::CSV));

        $rows = $this->loadQueuedRows();
        $this->assertCount(1, $rows);
        $this->assertSame('A-1', $rows[0]['sku']);
        $this->assertSame(
            [],
            $this->loadQueueEntries(ImportProcessingService::JOB_TYPE_CLEANUP),
            'the skipped row kept its identifier, so its existing element must not be cleaned up'
        );
    }

    public function testSkippedRowWithoutKeptIdentifierGetsCleanedUp(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PreQueueRowEvent::class, function (PreQueueRowEvent $event): void {
            if ($event->getOriginalRow()['sku'] === 'B-2') {
                $event->skipRow();
            }
        });

        $this->createInterpreter($dispatcher, doCleanup: true)->interpretFile($this->writeCsv(self::CSV));

        $cleanupEntries = $this->loadQueueEntries(ImportProcessingService::JOB_TYPE_CLEANUP);
        $this->assertCount(1, $cleanupEntries);
        $this->assertSame(
            'B-2',
            $cleanupEntries[0]['data'],
            'a skipped row without a kept identifier counts as removed from the source'
        );
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
