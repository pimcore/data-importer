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
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
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

    private const BROKEN_JOB_TYPE = 'broken';

    private const STOP_MESSAGE = 'Import stopped after an error';

    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

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

    public function testFailedItemStopsTheImport(): void
    {
        $this->seedConfiguration(['stopOnError' => true]);
        $this->queueItems([self::BROKEN_JOB_TYPE, ImportProcessingService::JOB_TYPE_PROCESS, ImportProcessingService::JOB_TYPE_PROCESS]);
        $this->queueItemOfOtherConfiguration();
        TmpStore::add(ImportProcessingService::INFO_ENTRY_ID_PREFIX . $this->configName, 3);
        $deltaChecker = new DeltaChecker(Db::get());
        $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']);

        $this->processSequentialQueue();

        static::assertCount(2, $this->errors);
        static::assertStringContainsString('Unknown job type ' . self::BROKEN_JOB_TYPE, $this->errors[0]);
        static::assertStringContainsString(self::STOP_MESSAGE . ': 2 queued items', $this->errors[1]);
        static::assertSame([], $this->infos, 'no item is processed after the error');
        static::assertSame(0, (new QueueService())->getQueueItemCount($this->configName));
        static::assertNull(TmpStore::get(ImportProcessingService::INFO_ENTRY_ID_PREFIX . $this->configName));
        static::assertTrue(
            $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']),
            'the delta check of the next run does not skip the discarded rows'
        );
        static::assertSame(1, (new QueueService())->getQueueItemCount($this->otherConfigName));
    }

    public function testFailedItemDoesNotStopTheImportByDefault(): void
    {
        $this->seedConfiguration([]);
        $this->queueItems([self::BROKEN_JOB_TYPE, ImportProcessingService::JOB_TYPE_PROCESS, ImportProcessingService::JOB_TYPE_PROCESS]);
        $deltaChecker = new DeltaChecker(Db::get());
        $deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']);

        $this->processSequentialQueue();

        static::assertCount(1, $this->errors);
        static::assertStringContainsString('Unknown job type ' . self::BROKEN_JOB_TYPE, $this->errors[0]);
        static::assertCount(2, $this->infos, 'the items after the error are processed');
        static::assertFalse($deltaChecker->hasChanged($this->configName, 'id', ['id' => 'row-1']));
    }

    public function testFailedLastItemLeavesNothingToStop(): void
    {
        $this->seedConfiguration(['stopOnError' => true]);
        $this->queueItems([ImportProcessingService::JOB_TYPE_PROCESS, self::BROKEN_JOB_TYPE]);

        $this->processSequentialQueue();

        static::assertCount(1, $this->errors);
        static::assertStringNotContainsString(self::STOP_MESSAGE, $this->errors[0]);
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
            $this->loggingConfigCache[$configName] = ['disableInfoFileObjects' => true, 'disableErrorFileObjects' => true];

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

    private function seedConfiguration(array $processingConfig): void
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
                'loadingStrategy' => ['type' => 'notLoad'],
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

    /**
     * @param string[] $jobTypes
     */
    private function queueItems(array $jobTypes): void
    {
        $queueService = new QueueService();
        foreach ($jobTypes as $index => $jobType) {
            $queueService->addItemToQueue(
                $this->configName,
                ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
                $jobType,
                json_encode(['id' => 'row-' . $index], JSON_THROW_ON_ERROR)
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

    private function processSequentialQueue(): void
    {
        $service = $this->createService(
            $this->tester->grabService(ResolverFactory::class),
            new DeltaChecker(Db::get()),
        );
        $queueService = new QueueService();
        $ids = array_filter(
            $queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL),
            fn ($id): bool => $queueService->getQueueEntryById((int) $id)['configName'] === $this->configName
        );
        foreach ($ids as $id) {
            $service->processQueueItem((int) $id);
        }
    }
}
