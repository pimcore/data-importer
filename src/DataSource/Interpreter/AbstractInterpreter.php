<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter;


use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataHubBatchImportBundle\PimcoreDataHubBatchImportBundle;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataHubBatchImportBundle\Queue\QueueService;
use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Resolver;
use Pimcore\Log\ApplicationLogger;
use Pimcore\Model\Tool\TmpStore;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractInterpreter implements InterpreterInterface
{
    use LoggerAwareTrait;

    /**
     * @var DeltaChecker
     */
    protected $deltaChecker;

    /**
     * @var QueueService
     */
    protected $queueService;

    /**
     * @var ApplicationLogger
     */
    protected $applicationLogger;

    /**
     * @var string
     */
    protected $configName;

    /**
     * @var bool
     */
    protected $doDeltaCheck;

    /**
     * @var mixed
     */
    protected $idDataIndex;

    /**
     * @var string
     */
    protected $executionType;

    /**
     * @var bool
     */
    protected $doCleanup;

    /**
     * @var bool
     */
    protected $doArchiveImportFile;

    /**
     * @var Resolver
     */
    protected $resolver;

    /**
     * @var string[]
     */
    protected $identifierCache;


    /**
     * AbstractInterpreter constructor.
     * @param DeltaChecker $deltaChecker
     * @param QueueService $queueService
     * @param ApplicationLogger $applicationLogger
     */
    public function __construct(DeltaChecker $deltaChecker, QueueService $queueService, ApplicationLogger $applicationLogger)
    {
        $this->deltaChecker = $deltaChecker;
        $this->queueService = $queueService;
        $this->applicationLogger = $applicationLogger;
    }

    /**
     * @return string
     */
    public function getConfigName(): string
    {
        return $this->configName;
    }

    /**
     * @param string $configName
     */
    public function setConfigName(string $configName): void
    {
        $this->configName = $configName;
    }

    /**
     * @return bool
     */
    public function doDeltaCheck(): bool
    {
        return $this->doDeltaCheck;
    }

    /**
     * @param bool $doDeltaCheck
     */
    public function setDoDeltaCheck(bool $doDeltaCheck): void
    {
        $this->doDeltaCheck = $doDeltaCheck;
    }

    /**
     * @return mixed
     */
    public function getIdDataIndex()
    {
        return $this->idDataIndex;
    }

    /**
     * @param mixed $idDataIndex
     */
    public function setIdDataIndex($idDataIndex): void
    {
        $this->idDataIndex = $idDataIndex;
    }

    /**
     * @return string
     */
    public function getExecutionType(): string
    {
        return $this->executionType;
    }

    /**
     * @param string $executionType
     */
    public function setExecutionType(string $executionType): void
    {
        $this->executionType = $executionType;
    }

    /**
     * @return bool
     */
    public function doCleanup(): bool
    {
        return $this->doCleanup;
    }

    /**
     * @param bool $doCleanup
     */
    public function setDoCleanup(bool $doCleanup): void
    {
        $this->doCleanup = $doCleanup;
    }

    /**
     * @return bool
     */
    public function doArchiveImportFile(): bool
    {
        return $this->doArchiveImportFile;
    }

    /**
     * @param bool $doArchiveImportFile
     */
    public function setDoArchiveImportFile(bool $doArchiveImportFile): void
    {
        $this->doArchiveImportFile = $doArchiveImportFile;
    }


    /**
     * @param Resolver $resolver
     */
    public function setResolver(Resolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    protected function processImportRow(array $data) {
        $createQueueItem = true;

        $this->addToIdentifierCache($data);

        //check delta
        if($this->doDeltaCheck) {
            $createQueueItem = $this->deltaChecker->hasChanged($this->configName, $this->idDataIndex, $data);
        }

        //create queue item
        if($createQueueItem) {
            $this->logger->debug(sprintf('Adding item `%s` of `%s` to processing queue.', ($data[$this->idDataIndex] ?? null), $this->configName));
            $this->queueService->addItemToQueue($this->configName, $this->executionType, ImportProcessingService::JOB_TYPE_PROCESS, json_encode($data));
        } else {
            $message = sprintf("Import data of item `%s` of `%s` didn't change, not adding to queue.", ($data[$this->idDataIndex] ?? null), $this->configName);
            $this->logger->debug($message);
            $this->applicationLogger->debug($message, [
                'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            ]);
        }
    }


    protected function resetIdentifierCache(): void {
        $this->identifierCache = [];
    }

    /**
     * @param array $data
     */
    protected function addToIdentifierCache(array $data): void {
        if($this->doCleanup) {
            $this->identifierCache[] = $this->resolver->extractIdentifierFromData($data);
        }
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function cleanupElements(): void {

        if(!$this->doCleanup) {
            return;
        }

        $existingElements = $this->resolver->loadFullIdentifierList();
        $elementsToCleanup = array_diff($existingElements, $this->identifierCache);

        foreach($elementsToCleanup as $identifier) {
            $this->logger->debug(sprintf('Adding item `%s` of `%s` to cleanup queue.', $identifier, $this->configName));
            $this->queueService->addItemToQueue($this->configName, $this->executionType, ImportProcessingService::JOB_TYPE_CLEANUP, $identifier);
        }

    }


    protected function updateExecutionPackageInformation() {

        $totalItem = $this->queueService->getQueueItemCount($this->configName);
        $infoEntryId = ImportProcessingService::INFO_ENTRY_ID_PREFIX . $this->configName;
        TmpStore::delete($infoEntryId);
        TmpStore::add($infoEntryId, $totalItem);

    }


}
