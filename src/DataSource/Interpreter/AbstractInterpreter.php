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

use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Model\Tool\TmpStore;
use Pimcore\Tool\Admin;
use Pimcore\Tool\Storage;
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
            $context = [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            ];

            $archivedFilePath = $this->archiveImportFile($path);
            if ($archivedFilePath !== null) {
                $context['fileObject'] = $archivedFilePath;
            }

            $this->applicationLogger->info($archiveLogMessage, $context);
        }

        $this->updateExecutionPackageInformation();

        return $success;
    }

    abstract protected function doInterpretFileAndCallProcessRow(string $path): void;

    /**
     * Streams the import file into the application-log storage instead of loading it into
     * memory via FileObject (large source files would otherwise exhaust the memory limit).
     * Returns the storage path in the same format FileObject produces, so the log viewer
     * can resolve it, or null when archiving failed.
     */
    private function archiveImportFile(string $path): ?string
    {
        $storagePath = date('/Y/m/d/') . uniqid('fileobject_', true);

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            $this->logger->warning(sprintf('Could not open import file `%s` for archiving.', $path));

            return null;
        }

        try {
            Storage::get('application_log')->writeStream($storagePath, $stream);
        } catch (FilesystemException | UnableToWriteFile $exception) {
            $this->logger->warning(sprintf('Could not archive import file to `%s`: %s', $storagePath, $exception->getMessage()));

            return null;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $storagePath;
    }

    protected function processImportRow(array $data)
    {
        $this->assertValidRowEncoding($data);

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
            $encodedData = json_encode($data);
            if ($encodedData === false) {
                // Backstop for data that mb_check_encoding() cannot reach (e.g. invalid bytes nested
                // deeper than the top-level columns). Fail loud instead of queueing an empty payload.
                throw new InvalidInputException(sprintf(
                    'Encoding error in `%s`: %s. Please make sure the source file is UTF-8 encoded.',
                    $this->configName,
                    json_last_error_msg()
                ));
            }

            $this->logger->debug(sprintf('Adding item `%s` of `%s` to processing queue.', ($data[$this->idDataIndex] ?? null), $this->configName));
            $this->queueService->addItemToQueue(
                $this->configName,
                $this->executionType,
                ImportProcessingService::JOB_TYPE_PROCESS,
                $encodedData,
                $userOwner
            );
        } else {
            $message = sprintf("Import data of item `%s` of `%s` didn't change, not adding to queue.", ($data[$this->idDataIndex] ?? null), $this->configName);
            $this->logger->debug($message);
            $this->applicationLogger->debug($message, [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            ]);
        }
    }

    /**
     * Detects character encoding problems (e.g. non-UTF-8 bytes) in a data row and fails loud
     * instead of silently dropping the row. This is detection only - no conversion is attempted,
     * as the importer cannot reliably guess the source encoding.
     *
     * @throws InvalidInputException
     */
    protected function assertValidRowEncoding(array $data): void
    {
        $invalidColumns = [];
        $position = 0;
        foreach ($data as $key => $value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                // Only keep the column label if it is itself safe to log (the header row may be
                // broken too); otherwise fall back to the numeric column position. Never put the
                // raw invalid bytes into the message: the application logger persists it into a
                // utf8mb4 column and would fail on malformed UTF-8.
                $invalidColumns[] = (is_string($key) && mb_check_encoding($key, 'UTF-8')) ? $key : ('#' . $position);
            }
            ++$position;
        }

        if ($invalidColumns !== []) {
            throw new InvalidInputException(sprintf(
                'Encoding error in `%s`: invalid UTF-8 characters in column(s) %s. '
                . 'Please make sure the source file is UTF-8 encoded.',
                $this->configName,
                implode(', ', $invalidColumns)
            ));
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
