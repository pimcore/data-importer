<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Processing;


use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\Exception\QueueNotEmptyException;
use Pimcore\Bundle\DataHubBatchImportBundle\PimcoreDataHubBatchImportBundle;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\Cron\CronExecutionService;
use Pimcore\Bundle\DataHubBatchImportBundle\Queue\QueueService;
use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\ResolverFactory;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\ConfigurationPreparationService;
use Pimcore\Log\ApplicationLogger;
use Psr\Log\LoggerAwareTrait;

class ImportPreparationService
{
    use LoggerAwareTrait;

    /**
     * @var ConfigurationPreparationService
     */
    protected $configLoader;

    /**
     * @var ResolverFactory
     */
    protected $resolverFactory;

    /**
     * @var InterpreterFactory
     */
    protected $interpreterFactory;

    /**
     * @var DataLoaderFactory
     */
    protected $dataLoaderFactory;

    /**
     * @var QueueService
     */
    protected $queueService;

    /**
     * @var ApplicationLogger
     */
    protected $applicationLogger;

    /**
     * @var CronExecutionService
     */
    protected $cronExecutionService;

    /**
     * ImportPreparationService constructor.
     * @param ResolverFactory $resolverFactory
     * @param InterpreterFactory $interpreterFactory
     * @param DataLoaderFactory $dataLoaderFactory
     * @param QueueService $queueService
     * @param ApplicationLogger $applicationLogger
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param CronExecutionService $cronExecutionService
     */
    public function __construct(ResolverFactory $resolverFactory, InterpreterFactory $interpreterFactory, DataLoaderFactory $dataLoaderFactory, QueueService $queueService, ApplicationLogger $applicationLogger, ConfigurationPreparationService $configurationPreparationService, CronExecutionService $cronExecutionService)
    {
        $this->resolverFactory = $resolverFactory;
        $this->interpreterFactory = $interpreterFactory;
        $this->dataLoaderFactory = $dataLoaderFactory;
        $this->queueService = $queueService;
        $this->applicationLogger = $applicationLogger;
        $this->configLoader = $configurationPreparationService;
        $this->cronExecutionService = $cronExecutionService;
    }

    /**
     * @param string $configName
     * @param bool $ignoreActiveFlag
     * @return bool
     */
    public function prepareImport(string $configName, bool $ignoreActiveFlag = false): bool {
        try {
            $queueItemCount = $this->queueService->getQueueItemCount($configName);
            if ($queueItemCount > 0) {
                throw new QueueNotEmptyException("Queue for `$configName` not empty. Not preparing new import, finish queue processing first.");
            }

            $config = $this->configLoader->prepareConfiguration($configName);

            if(!$ignoreActiveFlag && !$this->isConfigurationActive($configName, $config)) {
                return false;
            }

            $loader = $this->dataLoaderFactory->loadDataLoader($config['loaderConfig']);

            $logMessage = "Loading source data from configured source...";
            $this->applicationLogger->info($logMessage, [
                'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $configName,
            ]);
            $this->logger->info($logMessage);
            $filePath = $loader->loadData();
            $this->logger->info("Loaded source data from configured source.");

            $resolver = $this->resolverFactory->loadResolver($config['resolverConfig']);
            $interpreter = $this->interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig'], $resolver);

            $logMessage = "Interpreting source file and preparing queue items...";
            $this->logger->info($logMessage);
            $interpreter->interpretFile($filePath);
            $this->logger->info("Interpreted source file and prepared queue items.");

            $this->logger->info("Cleanup source file if necessary.");
            $loader->cleanup();
            $this->logger->info("Cleaned up source file if necessary.");

            return true;
        } catch (QueueNotEmptyException $e) {
            $message = "Error preparing Import: ". $e->getMessage();
            $this->logger->warning($message);

            $this->applicationLogger->warning($message, [
                'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $configName,
            ]);

        } catch (\Exception $e) {

            $message = "Error preparing Import: ";
            $this->logger->warning($message . $e);

            $this->applicationLogger->error($message . $e->getMessage(), [
                'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $configName,
            ]);

        }

        return false;

    }

    public function executeCron(string $configName) {

        $config = $this->configLoader->prepareConfiguration($configName);

        if(!($config['executionConfig']['cronDefinition'] ?? '')) {
            $message = "Configuration '$configName' has no cronDefinition, skipping cron execution.";
            $this->logger->debug($message);
            $this->applicationLogger->debug($message, [
                'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $configName
            ]);
            return;
        }

        if(!$this->isConfigurationActive($configName, $config)) {
            return;
        }

        if($this->cronExecutionService->getNextExecutionInPast($configName, $config['executionConfig']['cronDefinition'])) {
            $executionDateTime = new \DateTime();
            $this->prepareImport($configName);
            $this->cronExecutionService->updateExecutionTimestamp($configName, $executionDateTime);
        }

    }

    /**
     * @param string $configName
     * @param array $config
     * @return bool
     */
    public function isConfigurationActive(string $configName, array $config): bool {
        if(!($config['general']['active'] ?? false)) {
            $message = "Configuration '$configName' is not active, skipping preparation execution.";
            $this->logger->info($message);
            $this->applicationLogger->info($message, [
                'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $configName
            ]);
            return false;
        }

        return true;
    }

}
