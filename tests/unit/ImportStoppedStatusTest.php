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
use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\ExecutionService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportPreparationService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Db;
use Pimcore\Model\Tool\SettingsStore;
use Pimcore\Model\Tool\TmpStore;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ImportStoppedStatusTest extends Unit
{
    private const DATA_HUB_SCOPE = 'pimcore_data_hub';

    private string $configName;

    protected function _before(): void
    {
        $this->configName = uniqid('stopped-status-test-');
    }

    protected function _after(): void
    {
        SettingsStore::delete($this->configName, self::DATA_HUB_SCOPE);
        (new QueueService())->cleanupQueueItems($this->configName);
        TmpStore::delete(ImportProcessingService::STOPPED_ENTRY_ID_PREFIX . $this->configName);
        TmpStore::delete(ImportProcessingService::INFO_ENTRY_ID_PREFIX . $this->configName);
    }

    public function testStoppedImportIsReported(): void
    {
        $this->markStopped();

        static::assertTrue($this->importStatus()['stoppedAfterError']);
    }

    public function testRunningImportIsNotReportedAsStopped(): void
    {
        $this->markStopped();
        (new QueueService())->addItemToQueue(
            $this->configName,
            ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
            ImportProcessingService::JOB_TYPE_PROCESS,
            '{}'
        );

        static::assertFalse($this->importStatus()['stoppedAfterError']);
    }

    public function testCancellingClearsTheStoppedState(): void
    {
        $this->markStopped();

        $this->createProcessingService()->cancelImportAndCleanupQueue($this->configName);

        static::assertFalse($this->importStatus()['stoppedAfterError']);
    }

    public function testStartingAnImportClearsTheStoppedState(): void
    {
        $this->markStopped();
        // seeded like Data Hub stores it; without a data source the preparation fails right after it started
        SettingsStore::set(
            $this->configName,
            json_encode([
                'general' => ['active' => true, 'type' => 'dataImporterDataObject', 'name' => $this->configName],
            ], JSON_THROW_ON_ERROR),
            SettingsStore::TYPE_STRING,
            self::DATA_HUB_SCOPE
        );

        $preparation = new ImportPreparationService(
            new ResolverFactory(new Resolver(), [], [], [], []),
            new InterpreterFactory([]),
            new DataLoaderFactory([]),
            new QueueService(),
            $this->createStub(ApplicationLogger::class),
            new ConfigurationPreparationService(),
            new ExecutionService(),
            $this->createStub(EventDispatcherInterface::class),
        );
        $preparation->setLogger(new NullLogger());

        static::assertFalse($preparation->prepareImport($this->configName));
        static::assertFalse($this->importStatus()['stoppedAfterError']);
    }

    private function markStopped(): void
    {
        TmpStore::set(ImportProcessingService::STOPPED_ENTRY_ID_PREFIX . $this->configName, true);
    }

    private function importStatus(): array
    {
        return $this->createProcessingService()->getImportStatus($this->configName);
    }

    private function createProcessingService(): ImportProcessingService
    {
        $service = new ImportProcessingService(
            new QueueService(),
            new MappingConfigurationFactory(new MappingConfiguration(), [], []),
            new ResolverFactory(new Resolver(), [], [], [], []),
            new CleanupStrategyFactory([]),
            $this->createStub(ApplicationLogger::class),
            $this->createStub(EventDispatcherInterface::class),
            new DeltaChecker(Db::get()),
        );
        $service->setLogger(new NullLogger());

        return $service;
    }
}
