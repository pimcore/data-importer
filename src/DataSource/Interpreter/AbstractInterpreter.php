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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\ApplicationLoggerBundle\FileObject;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Model\Tool\TmpStore;
use Pimcore\Tool\Admin;
use Psr\Log\LoggerAwareTrait;

/**
 * @internal
 */
abstract class AbstractInterpreter implements InterpreterInterface
{
    use LoggerAwareTrait;

    protected string $configName;

    protected bool $doDeltaCheck;

    protected mixed $idDataIndex;

    protected string $executionType;

    protected bool $doCleanup;

    protected bool $doArchiveImportFile;

    protected Resolver $resolver;

    /**
     * @var string[]
     */
    protected array $identifierCache;

    public function __construct(
        protected readonly DeltaChecker $deltaChecker,
        protected readonly QueueService $queueService,
        protected readonly ApplicationLogger $applicationLogger,
    ) {
    }

    public function getConfigName(): string
    {
        return $this->configName;
    }

    public function setConfigName(string $configName): void
    {
        $this->configName = $configName;
    }

    public function doDeltaCheck(): bool
    {
        return $this->doDeltaCheck;
    }

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

    public function getExecutionType(): string
    {
        return $this->executionType;
    }

    public function setExecutionType(string $executionType): void
    {
        $this->executionType = $executionType;
    }

    public function doCleanup(): bool
    {
        return $this->doCleanup;
    }

    public function setDoCleanup(bool $doCleanup): void
    {
        $this->doCleanup = $doCleanup;
    }

    public function doArchiveImportFile(): bool
    {
        return $this->doArchiveImportFile;
    }

    public function setDoArchiveImportFile(bool $doArchiveImportFile): void
    {
        $this->doArchiveImportFile = $doArchiveImportFile;
    }

    public function setResolver(Resolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function interpretFile(string $path): bool
    {
        $success = false;
        $this->resetIdentifierCache();

        if ($this->fileValid($path)) {
            $archiveLogMessage = 'Interpreted source file and created queue items.';
            $this->doInterpretFileAndCallProcessRow($path);
            $this->cleanupElements();
            $success = true;
        } else {
            $archiveLogMessage = 'Uploaded file not valid.';
            $message = 'Uploaded file not valid, not creating any queue items and doing any cleanup."';
            $this->applicationLogger->error($message, [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName
            ]);
        }

        if ($this->doArchiveImportFile) {
            $this->applicationLogger->info($archiveLogMessage, [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                'fileObject' => new FileObject(file_get_contents($path))
            ]);
        }

        $this->updateExecutionPackageInformation();

        return $success;
    }

    abstract protected function doInterpretFileAndCallProcessRow(string $path): void;

    protected function processImportRow(array $data)
    {
        $createQueueItem = true;

        $this->addToIdentifierCache($data);

        //check delta
        if ($this->doDeltaCheck) {
            $createQueueItem = $this->deltaChecker->hasChanged($this->configName, $this->idDataIndex, $data);
        }

        // If there is no user logged in, we use the system user (ID 0) as userOwner
        $userOwner = Admin::getCurrentUser()?->getId() ?? 0;

        //create queue item
        if ($createQueueItem) {
            $this->logger->debug(sprintf('Adding item `%s` of `%s` to processing queue.', ($data[$this->idDataIndex] ?? null), $this->configName));
            $this->queueService->addItemToQueue($this->configName, $this->executionType, ImportProcessingService::JOB_TYPE_PROCESS, json_encode($data), $userOwner);
        } else {
            $message = sprintf("Import data of item `%s` of `%s` didn't change, not adding to queue.", ($data[$this->idDataIndex] ?? null), $this->configName);
            $this->logger->debug($message);
            $this->applicationLogger->debug($message, [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            ]);
        }
    }

    protected function resetIdentifierCache(): void
    {
        $this->identifierCache = [];
    }

    protected function addToIdentifierCache(array $data): void
    {
        if ($this->doCleanup) {
            $this->identifierCache[] = $this->resolver->extractIdentifierFromData($data);
        }
    }

    protected function cleanupElements(): void
    {
        if (!$this->doCleanup) {
            return;
        }

        $existingElements = $this->resolver->loadFullIdentifierList();
        $elementsToCleanup = array_diff($existingElements, $this->identifierCache);

        foreach ($elementsToCleanup as $identifier) {
            if ($identifier === null) {
                continue;
            }
            $this->logger->debug(sprintf('Adding item `%s` of `%s` to cleanup queue.', $identifier, $this->configName));
            $this->queueService->addItemToQueue($this->configName, $this->executionType, ImportProcessingService::JOB_TYPE_CLEANUP, $identifier);
        }
    }

    protected function updateExecutionPackageInformation()
    {
        $totalItem = $this->queueService->getQueueItemCount($this->configName);
        $infoEntryId = ImportProcessingService::INFO_ENTRY_ID_PREFIX . $this->configName;
        TmpStore::delete($infoEntryId);
        TmpStore::add($infoEntryId, $totalItem);
    }
}
