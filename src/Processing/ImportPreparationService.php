<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Processing;

use DateTime;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\Event\PostPreparationEvent;
use Pimcore\Bundle\DataImporterBundle\Exception\QueueNotEmptyException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Processing\Scheduler\Exception\InvalidScheduleException;
use Pimcore\Bundle\DataImporterBundle\Processing\Scheduler\SchedulerFactory;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class ImportPreparationService
{
    private const SCHEDULE_TYPE_CRON = 'cron';

    private const SCHEDULE_TYPE_JOB = 'job';

    use LoggerAwareTrait;

    /**
     * ImportPreparationService constructor.
     */
    public function __construct(
        private readonly ResolverFactory $resolverFactory,
        private readonly InterpreterFactory $interpreterFactory,
        private readonly DataLoaderFactory $dataLoaderFactory,
        private readonly QueueService $queueService,
        private readonly ApplicationLogger $applicationLogger,
        private readonly ConfigurationPreparationService $configLoader,
        private readonly ExecutionService $executionService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function prepareImport(
        string $configName,
        bool $ignoreActiveFlag = false,
        bool $ignoreNotEmptyQueueFlag = false
    ): bool {
        try {
            $queueItemCount = $this->queueService->getQueueItemCount($configName);
            if ($queueItemCount > 0 && !$ignoreNotEmptyQueueFlag) {
                throw new QueueNotEmptyException("Queue for `$configName` not empty. Not preparing new import, finish queue processing first.");
            }

            $config = $this->configLoader->prepareConfiguration($configName, null, true);

            if (!$ignoreActiveFlag && !$this->isConfigurationActive($configName, $config)) {
                return false;
            }

            $loader = $this->dataLoaderFactory->loadDataLoader($config['loaderConfig']);

            $logMessage = 'Loading source data from configured source...';
            $this->applicationLogger->info($logMessage, [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $configName,
            ]);
            $this->logger->info($logMessage);
            $filePath = $loader->loadData();
            $this->logger->info('Loaded source data from configured source.');

            $resolver = $this->resolverFactory->loadResolver($config['resolverConfig']);
            $interpreter = $this->interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'],
                $config['processingConfig'], $resolver);

            $logMessage = 'Interpreting source file and preparing queue items...';
            $this->logger->info($logMessage);
            $fileInterpreted = $interpreter->interpretFile($filePath);
            $this->logger->info('Interpreted source file and prepared queue items.');

            $this->logger->info('Cleanup source file if necessary.');
            $loader->cleanup();
            $this->logger->info('Cleaned up source file if necessary.');

            $this->eventDispatcher->dispatch(new PostPreparationEvent($configName, $config['processingConfig']['executionType'] ?? ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL, $fileInterpreted));

            return $fileInterpreted;
        } catch (QueueNotEmptyException $e) {
            $message = 'Error preparing Import: ' . $e->getMessage();
            $this->logger->warning($message);

            $this->applicationLogger->warning($message, [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $configName,
            ]);
        } catch (\Exception $e) {
            $message = 'Error preparing Import: ';
            $this->logger->warning($message . $e);

            $this->applicationLogger->error($message . $e->getMessage(), [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $configName,
            ]);
        }

        return false;
    }

    public function execute(string $configName)
    {
        $config = $this->configLoader->prepareConfiguration($configName, null, true);

        if (!$this->isConfigurationActive($configName, $config)) {
            return;
        }

        try {
            $scheduler = SchedulerFactory::create($config);
        } catch (InvalidScheduleException $e) {
            $message = "Configuration '$configName' is invalid: {$e->getMessage()}. Skipping job execution.";
            $this->logger->debug($message);

            return;
        }

        $executedAt = $this->executionService->getLastExecution($configName);

        if ($scheduler->isExecutable($executedAt)) {
            $executionDateTime = new DateTime();
            $this->prepareImport($configName);
            $this->executionService->updateExecutionTimestamp($configName, $executionDateTime);
        }
    }

    public function isConfigurationActive(string $configName, array $config): bool
    {
        if (!($config['general']['active'] ?? false)) {
            $message = "Configuration '$configName' is not active, skipping preparation execution.";
            $this->logger->info($message);

            return false;
        }

        return true;
    }
}
