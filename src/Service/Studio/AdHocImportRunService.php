<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\BranchMergeInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DecisionLock;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\FailedRowsCsv;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunQueueInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Lock\LockFactory;

/**
 * Starts the run of a file over the Data Importer's queue, in file order, and answers how it goes.
 *
 * @internal
 */
final readonly class AdHocImportRunService implements AdHocImportRunServiceInterface
{
    use CurrentUserResolverTrait;

    // the check of a large file takes minutes; a start that died gives its file free again after this
    private const float START_LOCK_SECONDS = 3600.0;

    public function __construct(
        private SecurityServiceInterface $securityService, // NOSONAR php:S1068 read by CurrentUserResolverTrait
        private AdHocImportDryRunServiceInterface $dryRunService,
        private RunStoreInterface $runs,
        private FileStore $files,
        private RunQueueInterface $queue,
        private RowWriterInterface $writer,
        private DryRunMessages $messages,
        private LockFactory $lockFactory,
        private ?BranchMergeInterface $branchMerge = null,
    ) {
    }

    public function start(string $fileId, AdHocImportRunParameters $parameters): AdHocImportRunResponse
    {
        $user = $this->resolveCurrentUser();
        // two requests at once would both find no run, and each would queue every row
        $lock = $this->lockFactory->createLock(
            sprintf('data_importer_ad_hoc_run_start_%d_%s', $user->getId(), $fileId),
            self::START_LOCK_SECONDS
        );
        if (!$lock->acquire()) {
            throw $this->beingImported($fileId);
        }

        try {
            return $this->startRun($user, $fileId, $parameters);
        } finally {
            $lock->release();
        }
    }

    private function startRun(
        User $user,
        string $fileId,
        AdHocImportRunParameters $parameters
    ): AdHocImportRunResponse {
        $earlier = $this->runs->find($user->getId(), $fileId);
        if ($earlier !== null && $earlier->state !== RunRecord::DONE) {
            throw $this->beingImported($fileId);
        }

        $settings = $parameters->getSettings();
        $checked = $this->dryRunService->check($fileId, $settings, $user);
        $imported = $this->importedRows($checked->results, $parameters->getErrorMode());
        if ($imported === []) {
            throw new InvalidArgumentException(sprintf('File %s has no row to import.', $fileId));
        }

        $type = $this->files->findType($user->getId(), $fileId)
            ?? throw new NotFoundHttpException(sprintf('File %s not found', $fileId));
        $run = new RunRecord(
            $user->getId(),
            $fileId,
            $type,
            $parameters->getFileName(),
            $settings->toArray(),
            $checked->plan->configuration,
            $parameters->getErrorMode(),
            count($imported),
            count($checked->results) - count($imported),
            $checked->plan->matchColumn,
            $checked->plan->dryRun->newObjectFolder,
            $checked->plan->newObjectFolderId,
            trim($user->getFirstname() . ' ' . $user->getLastname()) ?: $user->getName(),
            targetFolder: $checked->plan->dryRun->targetFolder
        );

        $this->runs->save($run);

        try {
            $run = $this->writer->open($run, $user);
            $this->runs->save($run);
            $this->queue->enqueue($run, $imported);
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 whatever stopped it, a run short of rows never ends
            $this->runs->delete($user->getId(), $fileId);
            $this->closeOpenedBranch($run, $user);

            throw $throwable;
        }

        return $this->response($run, []);
    }

    public function status(string $fileId): AdHocImportRunResponse
    {
        $user = $this->resolveCurrentUser();
        $run = $this->requireRun($user, $fileId);

        return $this->response($run, $this->failures($user, $fileId)['rows']);
    }

    public function failedRows(string $fileId): string
    {
        $user = $this->resolveCurrentUser();
        $failed = $this->failures($user, $fileId);
        if ($failed['rows'] === []) {
            throw new NotFoundHttpException(sprintf('No failed rows of file %s found', $fileId));
        }

        return FailedRowsCsv::of(
            $failed['columns'],
            $failed['rows'],
            $this->messages->columnName('row', $user),
            $this->messages->columnName('error', $user)
        );
    }

    public function discard(string $fileId): void
    {
        $user = $this->resolveCurrentUser();
        $this->deciding($user, $fileId, function () use ($user, $fileId): void {
            $run = $this->requireRun($user, $fileId);
            if ($run->isBusy()) {
                throw $this->beingImported($fileId);
            }

            if ($run->isWaitingForDecision()) {
                $this->closeBranch($run, $user);
                $this->files->delete($user->getId(), $fileId, $run->type);
            }
            $this->runs->delete($user->getId(), $fileId);
            $this->files->deleteRunOutput($user->getId(), $fileId);
        });
    }

    public function mergeLeavingOut(string $fileId): AdHocImportRunResponse
    {
        $user = $this->resolveCurrentUser();

        return $this->deciding($user, $fileId, function () use ($user, $fileId): AdHocImportRunResponse {
            $run = $this->branchMerge()->mergeLeavingOut($this->requireDecision($user, $fileId), $user);

            return $this->response($run, $this->failures($user, $fileId)['rows']);
        });
    }

    public function withdraw(string $fileId): void
    {
        $user = $this->resolveCurrentUser();
        $this->deciding($user, $fileId, function () use ($user, $fileId): void {
            $run = $this->requireDecision($user, $fileId);

            $this->closeBranch($run, $user);
            $this->runs->delete($user->getId(), $fileId);
            $this->files->deleteRunOutput($user->getId(), $fileId);
        });
    }

    public function waitingRuns(int $folderId): array
    {
        $user = $this->resolveCurrentUser();
        $waiting = array_filter(
            $this->runs->inState(RunRecord::DECISION),
            static fn (RunRecord $run): bool => $run->userId === $user->getId()
                && (int) ($run->parameters['folderId'] ?? 0) === $folderId
        );

        return array_values(array_map(
            static fn (RunRecord $run): array => ['fileId' => $run->fileId, 'fileName' => $run->fileName],
            $waiting
        ));
    }

    /**
     * @template T
     *
     * @param callable(): T $decide reads the run itself: what was read before the lock may be out of date
     *
     * @return T
     */
    private function deciding(User $user, string $fileId, callable $decide): mixed
    {
        $lock = DecisionLock::of($this->lockFactory, $user->getId(), $fileId);
        if (!$lock->acquire()) {
            throw $this->beingImported($fileId);
        }

        try {
            return $decide();
        } finally {
            $lock->release();
        }
    }

    private function requireDecision(User $user, string $fileId): RunRecord
    {
        $run = $this->requireRun($user, $fileId);
        if (!$run->isWaitingForDecision()) {
            throw new InvalidArgumentException(sprintf('The import of %s waits for no decision.', $fileId));
        }

        return $run;
    }

    private function closeBranch(RunRecord $run, User $user): void
    {
        if ($run->throughBranch()) {
            $this->branchMerge()->discard($run, $user);
        }
    }

    /**
     * A start that failed leaves no branch open: nothing else knows of it any more.
     */
    private function closeOpenedBranch(RunRecord $run, User $user): void
    {
        if (!$run->throughBranch() || $this->branchMerge === null) {
            return;
        }

        try {
            $this->branchMerge->discard($run, $user);
        } catch (\Throwable) { // NOSONAR php:S2221 the user learns what stopped the start, not what kept the branch
            // the branch stays open; what stopped the start is thrown on
        }
    }

    private function branchMerge(): BranchMergeInterface
    {
        return $this->branchMerge ?? throw new InvalidArgumentException('Change Control is not installed.');
    }

    private function beingImported(string $fileId): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('File %s is being imported.', $fileId));
    }

    /**
     * @param list<DryRunRow> $results
     *
     * @return array<int, list<int>> per position in the file of the rows to import, the writers it goes without
     */
    private function importedRows(array $results, string $errorMode): array
    {
        $imported = [];
        foreach ($results as $index => $result) {
            if ($errorMode === AdHocImportDryRunServiceInterface::SKIP_ROWS) {
                if (!$result->hasErrors()) {
                    $imported[$index] = [];
                }
            } elseif ($result->importsWithoutInvalidValues()) {
                $imported[$index] = $result->leftOut;
            }
        }

        return $imported;
    }

    private function requireRun(User $user, string $fileId): RunRecord
    {
        return $this->runs->find($user->getId(), $fileId)
            ?? throw new NotFoundHttpException(sprintf('No run of file %s found', $fileId));
    }

    /**
     * @return array{
     *     columns: list<string>,
     *     rows: list<array{
     *         row: int,
     *         matchValue: string,
     *         action: string|null,
     *         message: string,
     *         values: list<string>
     *     }>
     * }
     */
    private function failures(User $user, string $fileId): array
    {
        $stored = $this->files->readFailedRows($user->getId(), $fileId);
        $failed = $stored === null ? null : json_decode($stored, true);

        return is_array($failed) ? $failed : ['columns' => [], 'rows' => []];
    }

    /**
     * @param list<array{
     *     row: int,
     *     matchValue: string,
     *     action: string|null,
     *     message: string,
     *     values: list<string>
     * }> $rows
     */
    private function response(RunRecord $run, array $rows): AdHocImportRunResponse
    {
        return new AdHocImportRunResponse(
            $run->state,
            $run->total,
            $run->processed(),
            $run->updated,
            $run->created,
            $run->failed,
            $run->skipped,
            $run->errorMode,
            $run->fileName,
            $run->matchColumn,
            $run->newObjectsFolder,
            $run->newObjectsFolderId,
            $run->userName,
            $run->finishedAt,
            $run->throughBranch(),
            $run->mergeTotal,
            $run->mergeDone,
            $run->notice,
            array_map(static fn (array $row): array => [
                'row' => $row['row'],
                'matchValue' => $row['matchValue'],
                'action' => $row['action'],
                'message' => $row['message'],
            ], $rows)
        );
    }
}
