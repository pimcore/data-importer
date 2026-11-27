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

use Closure;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\Stub;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Factory\FactoryInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\DoNotCreateStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\LocationStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\PublishStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Db;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Tool\SettingsStore;
use Pimcore\Model\Tool\TmpStore;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StopOnErrorTest extends Unit
{
    private const CONFIG_NAME = 'stop-on-error-test';

    private const DATA_HUB_SCOPE = 'pimcore_data_hub';

    private const LOAD_STRATEGY = 'failOnRow';

    private const FAILING_ROW = 'failing-row';

    private const FAILURE = 'loading failed';

    private const STOP_MESSAGE = 'Import stopped after an error';

    // unique per test, Data Hub caches configurations for the process
    private ?string $configName = null;

    private ?string $otherConfigName = null;

    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $infos = [];

    protected function _after(): void
    {
        foreach (array_filter([$this->configName, $this->otherConfigName]) as $configName) {
            SettingsStore::delete($configName, self::DATA_HUB_SCOPE);
            (new QueueService())->cleanupQueueItems($configName);
            (new DeltaChecker(Db::get()))->cleanup($configName);
            TmpStore::delete(ImportProcessingService::INFO_ENTRY_ID_PREFIX . $configName);
            TmpStore::delete(ImportProcessingService::STOPPED_ENTRY_ID_PREFIX . $configName);
        }
    }

    public function testFailedRowIsReportedAsFailed(): void
    {
        $element = $this->createElement();
        $element->method('save')->willThrowException(new \RuntimeException('save failed'));

        static::assertFalse($this->processRow($element));
    }

    public function testSavedRowIsNotReportedAsFailed(): void
    {
        $element = $this->createElement();
        $element->method('save')->willReturnSelf();

        static::assertTrue($this->processRow($element));
    }

    public function testRowSkippedByPreSaveListenerIsNotReportedAsFailed(): void
    {
        $element = $this->createMock(ElementInterface::class);
        $element->expects($this->never())->method('save');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (object $event): object {
            if ($event instanceof PreSaveEvent) {
                $event->setSkipSave(true);
            }

            return $event;
        });

        static::assertTrue($this->processRow($element, $this->createService(eventDispatcher: $eventDispatcher)));
    }

    public function testRowWithoutMatchingElementIsNotReportedAsFailed(): void
    {
        static::assertTrue($this->processRow(null));
    }

    public function testFailedCleanupIsReportedAsFailed(): void
    {
        $cleanupConfig = ['doCleanup' => true, 'strategy' => 'does-not-exist'];

        static::assertFalse($this->cleanupRow($this->createElement(), $cleanupConfig));
    }

    public function testDisabledCleanupIsNotReportedAsFailed(): void
    {
        static::assertTrue($this->cleanupRow($this->createElement(), ['doCleanup' => false]));
    }

    public function testFailedRecordStopsTheImport(): void
    {
        $this->seedConfiguration(['stopOnError' => true]);
        $this->queueRecords(self::FAILING_ROW, 'row-1', 'row-2');
        $this->queueCleanupJobs('element-1');
        $this->queueItemOfOtherConfiguration();
        TmpStore::add(ImportProcessingService::INFO_ENTRY_ID_PREFIX . $this->configName, 4);
        $deltaChecker = new DeltaChecker(Db::get());
        $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']);

        $this->processSequentialQueue();

        static::assertCount(2, $this->errors);
        static::assertStringContainsString(self::FAILURE, $this->errors[0]);
        static::assertStringContainsString(self::STOP_MESSAGE . ': 3 queued items', $this->errors[1]);
        static::assertSame([], $this->infos, 'no item is processed after the failing record');
        static::assertSame(0, (new QueueService())->getQueueItemCount($this->configName));
        static::assertNull(TmpStore::get(ImportProcessingService::INFO_ENTRY_ID_PREFIX . $this->configName));
        static::assertTrue(
            $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']),
            'the delta check of the next run does not skip the discarded records'
        );
        static::assertSame(1, (new QueueService())->getQueueItemCount($this->otherConfigName));
        static::assertTrue($this->createService()->getImportStatus($this->configName)['stoppedAfterError']);
    }

    public function testFailedRecordDoesNotStopTheImportByDefault(): void
    {
        $this->seedConfiguration([]);
        $this->queueRecords(self::FAILING_ROW, 'row-1', 'row-2');
        $deltaChecker = new DeltaChecker(Db::get());
        $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']);

        $this->processSequentialQueue();

        static::assertCount(1, $this->errors);
        static::assertStringContainsString(self::FAILURE, $this->errors[0]);
        static::assertCount(2, $this->infos, 'the records after the failing one are processed');
        static::assertFalse($deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']));
        static::assertFalse($this->createService()->getImportStatus($this->configName)['stoppedAfterError']);
    }

    public function testFailedCleanupJobStopsTheImport(): void
    {
        $this->seedConfiguration([
            'stopOnError' => true,
            'cleanup' => ['doCleanup' => true, 'strategy' => 'unpublish'],
        ]);
        $this->queueCleanupJobs(self::FAILING_ROW, 'element-1');
        $deltaChecker = new DeltaChecker(Db::get());
        $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']);

        $this->processSequentialQueue();

        static::assertCount(2, $this->errors);
        static::assertStringContainsString(self::FAILURE, $this->errors[0]);
        static::assertStringContainsString(self::STOP_MESSAGE . ': 1 queued items', $this->errors[1]);
        static::assertSame(0, (new QueueService())->getQueueItemCount($this->configName));
        static::assertFalse(
            $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']),
            'no record was discarded, so the delta check keeps its hashes'
        );
        static::assertTrue($this->createService()->getImportStatus($this->configName)['stoppedAfterError']);
    }

    public function testFailedCleanupJobWithQueuedRecordsResetsTheDeltaCheck(): void
    {
        $this->seedConfiguration([
            'stopOnError' => true,
            'cleanup' => ['doCleanup' => true, 'strategy' => 'unpublish'],
        ]);
        $this->queueCleanupJobs(self::FAILING_ROW);
        $this->queueRecords('row-1');
        $deltaChecker = new DeltaChecker(Db::get());
        $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']);

        $this->processSequentialQueue();

        static::assertStringContainsString(self::STOP_MESSAGE . ': 1 queued items', $this->errors[1]);
        static::assertTrue(
            $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']),
            'the discarded record is not skipped by the next run'
        );
    }

    public function testFailedLastRecordIsRetriedByTheNextRun(): void
    {
        $this->seedConfiguration(['stopOnError' => true]);
        $this->queueRecords('row-1', self::FAILING_ROW);
        $deltaChecker = new DeltaChecker(Db::get());
        $deltaChecker->hasChanged($this->configName, 'id', ['id' => self::FAILING_ROW]);

        $this->processSequentialQueue();

        static::assertCount(1, $this->errors, 'nothing was left to discard');
        static::assertStringContainsString(self::FAILURE, $this->errors[0]);
        static::assertTrue($deltaChecker->hasChanged($this->configName, 'id', ['id' => self::FAILING_ROW]));
        static::assertFalse(
            $this->createService()->getImportStatus($this->configName)['stoppedAfterError'],
            'nothing was discarded, so the import ran through'
        );
    }

    public function testErrorOutsideTheJobStopsTheImport(): void
    {
        $this->seedConfiguration(['stopOnError' => true], ['throwError' => true]);
        $this->queueRecords('row-1', 'row-2');

        try {
            $this->processSequentialQueue();
            static::fail('the error is passed on');
        } catch (\Error $error) {
            static::assertSame(self::FAILURE, $error->getMessage());
        }

        static::assertCount(1, $this->errors);
        static::assertStringContainsString(self::STOP_MESSAGE . ': 1 queued items', $this->errors[0]);
        static::assertSame(0, (new QueueService())->getQueueItemCount($this->configName));
        static::assertTrue($this->createService()->getImportStatus($this->configName)['stoppedAfterError']);
    }

    public function testErrorOutsideTheJobDoesNotStopTheImportByDefault(): void
    {
        $this->seedConfiguration([], ['throwError' => true]);
        $this->queueRecords('row-1', 'row-2');

        try {
            $this->processSequentialQueue();
            static::fail('the error is passed on');
        } catch (\Error $error) {
            static::assertSame(self::FAILURE, $error->getMessage());
        }

        static::assertSame([], $this->errors);
        static::assertSame(
            1,
            (new QueueService())->getQueueItemCount($this->configName),
            'the second row stays queued'
        );
    }

    public function testFailingStopKeepsTheFailingItemForTheNextAttempt(): void
    {
        $this->seedConfiguration(['stopOnError' => true]);
        $this->queueRecords(self::FAILING_ROW, 'row-1');
        $unavailable = $this->createStub(Connection::class);
        $unavailable->method('executeQuery')->willThrowException(new \RuntimeException('database unavailable'));

        try {
            $this->processSequentialQueue(new DeltaChecker($unavailable));
            static::fail('the failed stop is passed on');
        } catch (\RuntimeException $exception) {
            static::assertSame('database unavailable', $exception->getMessage());
        }
        static::assertSame(2, (new QueueService())->getQueueItemCount($this->configName));

        $this->processSequentialQueue();

        static::assertStringContainsString(self::STOP_MESSAGE . ': 1 queued items', end($this->errors));
        static::assertSame(0, (new QueueService())->getQueueItemCount($this->configName));
    }

    private function createElement(): ElementInterface&Stub
    {
        $element = $this->createStub(ElementInterface::class);
        $element->method('setUserModification')->willReturnSelf();

        return $element;
    }

    private function createService(
        ?ResolverFactory $resolverFactory = null,
        ?DeltaChecker $deltaChecker = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): ImportProcessingService {
        $applicationLogger = $this->createStub(ApplicationLogger::class);
        $applicationLogger->method('error')->willReturnCallback(function ($message): void {
            $this->errors[] = (string) $message;
        });
        $applicationLogger->method('info')->willReturnCallback(function ($message): void {
            $this->infos[] = (string) $message;
        });

        $service = new ImportProcessingService(
            new QueueService(),
            new MappingConfigurationFactory(new MappingConfiguration(), [], []),
            $resolverFactory ?? new ResolverFactory(new Resolver(), [], [], [], []),
            new CleanupStrategyFactory([]),
            $applicationLogger,
            $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
            $deltaChecker ?? new DeltaChecker($this->createStub(Connection::class)),
        );
        $service->setLogger(new NullLogger());

        return $service;
    }

    private function processRow(?ElementInterface $element, ?ImportProcessingService $service = null): bool
    {
        $service = $service ?? $this->createService();
        $resolver = $this->createResolver($element);
        $configName = self::CONFIG_NAME;

        return Closure::bind(function () use ($configName, $resolver): bool {
            $this->loggingConfigCache[$configName] = [
                'disableInfoFileObjects' => true,
                'disableErrorFileObjects' => true,
            ];

            return $this->processElement($configName, ['id-1'], $resolver, [], 0, []);
        }, $service, ImportProcessingService::class)();
    }

    private function cleanupRow(ElementInterface $element, array $cleanupConfig): bool
    {
        $service = $this->createService();
        $resolver = $this->createResolver($element);
        $configName = self::CONFIG_NAME;

        return Closure::bind(function () use ($configName, $resolver, $cleanupConfig): bool {
            return $this->cleanupElement($configName, 'id-1', $resolver, $cleanupConfig);
        }, $service, ImportProcessingService::class)();
    }

    private function createResolver(?ElementInterface $element): Resolver
    {
        $loadingStrategy = $this->createStub(LoadStrategyInterface::class);
        $loadingStrategy->method('loadElement')->willReturn($element);
        $loadingStrategy->method('loadElementByIdentifier')->willReturn($element);

        $resolver = new Resolver();
        $resolver->setLoadingStrategy($loadingStrategy);
        $resolver->setLocationUpdateStrategy($this->createStub(LocationStrategyInterface::class));
        $resolver->setCreateLocationStrategy(new DoNotCreateStrategy());
        $resolver->setPublishingStrategy($this->createStub(PublishStrategyInterface::class));

        return $resolver;
    }

    private function seedConfiguration(array $processingConfig, array $loadingSettings = []): void
    {
        $this->configName = uniqid('stop-on-error-test-');
        // seeded like Data Hub stores it; saving a Configuration needs the Data Hub workspace tables
        $configuration = [
            'general' => [
                'active' => true,
                'type' => 'dataImporterDataObject',
                'name' => $this->configName,
                'path' => '',
            ],
            'resolverConfig' => [
                'elementType' => 'dataObject',
                'dataObjectClassId' => 'stop-on-error-test',
                'loadingStrategy' => ['type' => self::LOAD_STRATEGY, 'settings' => $loadingSettings],
                'createLocationStrategy' => ['type' => 'doNotCreate'],
                'locationUpdateStrategy' => ['type' => 'noChange'],
                'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
            ],
            'processingConfig' => $processingConfig + [
                'logging' => ['disableInfoFileObjects' => true, 'disableErrorFileObjects' => true],
            ],
            'mappingConfig' => [],
        ];
        SettingsStore::set(
            $this->configName,
            json_encode($configuration, JSON_THROW_ON_ERROR),
            SettingsStore::TYPE_STRING,
            self::DATA_HUB_SCOPE
        );
    }

    // its load strategy fails on FAILING_ROW, finds nothing else, and throws an \Error when configured with throwError
    private function createResolverFactory(): ResolverFactory
    {
        $loadStrategy = new class(self::FAILING_ROW, self::FAILURE) implements LoadStrategyInterface {
            public function __construct(
                private readonly string $failingId,
                private readonly string $failure,
            ) {
            }

            public function setSettings(array $settings): void
            {
                if ($settings['throwError'] ?? false) {
                    throw new \Error($this->failure);
                }
            }

            public function loadElement(array $inputData): ?ElementInterface
            {
                return $this->loadElementByIdentifier($inputData['id'] ?? null);
            }

            public function loadElementByIdentifier($identifier): ?ElementInterface
            {
                if ($identifier === $this->failingId) {
                    throw new InvalidInputException($this->failure);
                }

                return null;
            }

            public function extractIdentifierFromData(array $inputData)
            {
                return $inputData['id'] ?? null;
            }

            public function loadFullIdentifierList(): array
            {
                return [];
            }

            public function setDataObjectClassId($dataObjectClassId): void
            {
                // this load strategy never loads by class
            }
        };

        return new ResolverFactory(
            new Resolver(),
            [self::LOAD_STRATEGY => $loadStrategy],
            [
                'doNotCreate' => new DoNotCreateStrategy(),
                'noChange' => $this->createStub(LocationStrategyInterface::class),
            ],
            ['noChangeUnpublishNew' => $this->createStub(PublishStrategyInterface::class)],
            ['dataObject' => $this->createStub(FactoryInterface::class)],
        );
    }

    private function queueRecords(string ...$ids): void
    {
        foreach ($ids as $id) {
            (new QueueService())->addItemToQueue(
                $this->configName,
                ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
                ImportProcessingService::JOB_TYPE_PROCESS,
                json_encode(['id' => $id], JSON_THROW_ON_ERROR)
            );
        }
    }

    private function queueCleanupJobs(string ...$identifiers): void
    {
        foreach ($identifiers as $identifier) {
            (new QueueService())->addItemToQueue(
                $this->configName,
                ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
                ImportProcessingService::JOB_TYPE_CLEANUP,
                $identifier
            );
        }
    }

    private function queueItemOfOtherConfiguration(): void
    {
        $this->otherConfigName = uniqid('stop-on-error-other-');
        (new QueueService())->addItemToQueue(
            $this->otherConfigName,
            ImportProcessingService::EXECUTION_TYPE_PARALLEL,
            ImportProcessingService::JOB_TYPE_PROCESS,
            '{}'
        );
    }

    private function processSequentialQueue(?DeltaChecker $deltaChecker = null): void
    {
        $service = $this->createService($this->createResolverFactory(), $deltaChecker ?? new DeltaChecker(Db::get()));
        $queueService = new QueueService();
        $ids = array_filter(
            $queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL),
            fn ($id): bool => $queueService->getQueueEntryById((int) $id)['configName'] === $this->configName
        );
        sort($ids);
        foreach ($ids as $id) {
            $service->processQueueItem((int) $id);
        }
    }
}
