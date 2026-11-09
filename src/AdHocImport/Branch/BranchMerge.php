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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\BranchMergeInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\MergeFollowMessage;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowFailure;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowImporter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunNotifier;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Contracts\ChangeControl\BranchServiceInterface;
use Pimcore\Contracts\ChangeControl\ConflictPolicy;
use Pimcore\Contracts\ChangeControl\Exception\BranchNotFoundException;
use Pimcore\Contracts\ChangeControl\Exception\ChangeControlExceptionInterface;
use Pimcore\Contracts\ChangeControl\MergeMode;
use Pimcore\Contracts\ChangeControl\MergeProblem;
use Pimcore\Contracts\ChangeControl\MergeState;
use Pimcore\Contracts\ChangeControl\MergeStatus;
use Pimcore\Contracts\ChangeControl\ObjectRef;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Merges a run's branch as the user who started it, at once when every row went in. Changed objects keep whether they
 * are published and new ones stay unpublished; the file wins over what changed on an object meanwhile. What the merge
 * cannot save is told per row of the file, in the import's own words.
 *
 * @internal
 */
final readonly class BranchMerge implements BranchMergeInterface
{
    // how often a worker looks how a merge goes
    private const int FOLLOW_DELAY_MS = 2000;

    // a merge that writes nothing for this long is given up: a run must not show as running for good
    private const int STALLED_AFTER_SECONDS = 1800;

    public function __construct(
        private RunStoreInterface $runs,
        private FileStore $files,
        private RunNotifier $notifier,
        private RunMessages $messages,
        private MessageBusInterface $bus,
        private EventDispatcherInterface $eventDispatcher,
        private ConcreteObjectResolverInterface $objects,
        private UserResolverInterface $users,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
        private ?BranchServiceInterface $branches = null,
    ) {
    }

    public function rowsWritten(RunRecord $run, ?ImportPlan $plan, ?User $user, array $failures): void
    {
        if ($plan !== null) {
            $run = $run->withColumns(array_values($plan->columns));
        }

        if (!$user instanceof User || $run->updated + $run->created === 0) {
            // nobody is left to merge as, or nothing went into the branch
            if ($user instanceof User) {
                $this->discard($run, $user);
            }
            $this->finish($run, $failures, $user);

            return;
        }

        if ($failures !== []) {
            $this->waitForDecision($run, $failures, $user);

            return;
        }

        $this->startMerge($run, $user);
    }

    public function follow(int $userId, string $fileId): void
    {
        // a worker and the maintenance may both look: only one goes on with what the merge came to
        $lock = $this->lockFactory->createLock(sprintf('data_importer_ad_hoc_merge_%d_%s', $userId, $fileId));
        if (!$lock->acquire()) {
            return;
        }

        try {
            $this->followLocked($userId, $fileId);
        } finally {
            $lock->release();
        }
    }

    private function followLocked(int $userId, string $fileId): void
    {
        $run = $this->runs->find($userId, $fileId);
        if ($run === null || $run->state !== RunRecord::MERGING) {
            return;
        }

        $user = $this->users->getById($userId);
        if (!$user instanceof User) {
            $this->finish($run, $this->storedFailures($run), null);

            return;
        }

        $status = $this->branches()->mergeStatus(BranchRowWriter::branchOf($run), BranchRowWriter::context($run), $user);
        if ($status === null) {
            $this->failRemaining($run, $user, $this->messages->mergeInterrupted($user));

            return;
        }

        $this->apply($run, $status, $user);
    }

    public function mergeLeavingOut(RunRecord $run, User $user): RunRecord
    {
        if (!$run->isWaitingForDecision()) {
            throw new \LogicException(sprintf('The import of %s waits for no decision.', $run->fileId));
        }

        $failed = array_map(static fn (RowFailure $failure): int => $failure->row, $this->storedFailures($run));
        $recorded = $this->runs->recorded($run);
        if ($recorded === [] && $run->updated + $run->created > 0) {
            // the rows in the branch are no longer known: none of them can be merged, so none of them is saved
            $this->discard($run, $user);

            return $this->finish(
                $run->nothingSaved()->withNotice($this->messages->cannotGoOn($user)),
                $this->storedFailures($run),
                $user
            );
        }
        $leftOut = array_values(array_filter(
            $recorded,
            static fn (RecordedRow $row): bool => in_array($row->row, $failed, true)
        ));

        if ($leftOut !== []) {
            $this->branches()->takeOut(
                BranchRowWriter::branchOf($run),
                BranchRowWriter::context($run),
                $user,
                array_map(self::refOf(...), $leftOut)
            );
            $this->runs->removeRecorded($run, array_map(static fn (RecordedRow $row): int => $row->row, $leftOut));
        }

        $run = $run->withNotice(null);
        if (count($recorded) === count($leftOut)) {
            $this->discard($run, $user);

            return $this->finish($run, $this->storedFailures($run), $user);
        }

        return $this->startMerge($run, $user);
    }

    public function discard(RunRecord $run, User $user): void
    {
        $this->closeBranch($run, $user);
        $this->runs->forgetRecorded($run);
    }

    private function closeBranch(RunRecord $run, User $user): void
    {
        try {
            $this->branches()->discard(BranchRowWriter::branchOf($run), BranchRowWriter::context($run), $user);
        } catch (BranchNotFoundException) {
            // gone already: nothing is left to close
        }
    }

    private function closeBranchLogged(RunRecord $run, User $user): void
    {
        try {
            $this->closeBranch($run, $user);
        } catch (ChangeControlExceptionInterface $notClosed) {
            $this->log($run, $notClosed->getMessage());
        }
    }

    private function startMerge(RunRecord $run, User $user): RunRecord
    {
        try {
            $status = $this->branches()->merge(
                BranchRowWriter::branchOf($run),
                BranchRowWriter::context($run),
                $user,
                MergeMode::Save,
                ConflictPolicy::BranchWins
            );
        } catch (ChangeControlExceptionInterface $exception) {
            $this->log($run, $exception->getMessage());
            $this->closeBranchLogged($run, $user);

            return $this->failRemaining($run, $user, $this->messages->mergeProblem('other', null, $user));
        }

        return $this->apply($run, $status, $user);
    }

    private function apply(RunRecord $run, MergeStatus $status, User $user): RunRecord
    {
        return match ($status->state) {
            MergeState::Running => $this->goingOn($run, $status, $user),
            MergeState::Refused => $this->refused($run, $status, $user),
            MergeState::Merged, MergeState::Partial => $this->merged($run, $status, $user),
        };
    }

    private function goingOn(RunRecord $run, MergeStatus $status, User $user): RunRecord
    {
        $now = time();
        $run = $run->merging($status->total, $status->done, $now);
        if ($run->stalled($now, self::STALLED_AFTER_SECONDS)) {
            $timedOut = $this->messages->mergeTimedOut($user);

            return $this->failRemaining($run->withNotice($timedOut), $user, $timedOut);
        }

        $this->runs->save($run);

        try {
            $this->bus->dispatch(
                new MergeFollowMessage($run->userId, $run->fileId),
                [new DelayStamp(self::FOLLOW_DELAY_MS)]
            );
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 the run stays merging; the merge goes on without it
            $this->log($run, $throwable->getMessage());
        }

        return $run;
    }

    /**
     * Nothing was written and the branch is open: the rows that cannot be saved wait for the user's decision.
     */
    private function refused(RunRecord $run, MergeStatus $status, User $user): RunRecord
    {
        [$messages, $notice] = $this->rowProblems($run, $status->problems, $user);
        if ($messages === []) {
            // no row to leave out: merging again would be refused the same way
            $notice ??= $this->messages->mergeProblem('other', null, $user);
            $this->closeBranchLogged($run, $user);

            return $this->failRemaining($run->withNotice($notice), $user, $notice);
        }

        $failures = $this->storedFailures($run);
        foreach ($this->runs->recorded($run) as $row) {
            if (isset($messages[$row->row])) {
                $run = $run->failedAfterWrite($row->action);
                $failures[] = $row->failure($messages[$row->row]);
            }
        }

        return $this->waitForDecision($run->withNotice($notice), $failures, $user);
    }

    /**
     * What was written stays and the branch is closed; a row whose object the merge did not get to is not saved.
     */
    private function merged(RunRecord $run, MergeStatus $status, User $user): RunRecord
    {
        [$messages, $notice] = $this->rowProblems($run, $status->problems, $user);
        $saved = [];
        foreach ([...$status->written, ...$status->leftOut] as $object) {
            foreach (self::keysOf($object) as $key) {
                $saved[$key] = true;
            }
        }

        $failures = $this->storedFailures($run);
        $written = [];
        foreach ($this->runs->recorded($run) as $row) {
            if (isset($saved[self::keyOf($row)])) {
                $written[] = $row;

                continue;
            }
            $run = $run->failedAfterWrite($row->action);
            $failures[] = $row->failure($messages[$row->row] ?? $this->messages->mergeInterrupted($user));
        }

        $this->afterSave($run, $status->written, $written);

        return $this->finish($run->withNotice($notice), $failures, $user);
    }

    /**
     * Every row still in the branch fails with the same reason, and the run ends.
     */
    private function failRemaining(RunRecord $run, User $user, string $message): RunRecord
    {
        $failures = $this->storedFailures($run);
        foreach ($this->runs->recorded($run) as $row) {
            $run = $run->failedAfterWrite($row->action);
            $failures[] = $row->failure($message);
        }

        return $this->finish($run, $failures, $user);
    }

    /**
     * @param list<RowFailure> $failures
     */
    private function waitForDecision(RunRecord $run, array $failures, User $user): RunRecord
    {
        $failures = self::byRow($failures);
        $this->storeFailures($run, $failures);
        $run = $run->waitingForDecision(time());
        $this->runs->save($run);
        $this->notifier->notifyDecision($run, $failures, $user);

        return $run;
    }

    /**
     * @param list<RowFailure> $failures
     */
    private function finish(RunRecord $run, array $failures, ?User $user): RunRecord
    {
        $failures = self::byRow($failures);
        if ($failures !== []) {
            $this->storeFailures($run, $failures);
        }
        $run = $run->finished(time());
        $this->runs->save($run);
        $this->runs->forgetRecorded($run);

        try {
            $this->files->deleteUpload($run->userId, $run->fileId, $run->type);
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 the run has ended, whatever keeps its file
            $this->log($run, $throwable->getMessage());
        }
        if ($user instanceof User) {
            $this->notifier->notify($run, $failures, $user);
        }

        return $run;
    }

    /**
     * The events of an import, for the objects the merge wrote: a listener's failure does not undo the merge.
     *
     * @param list<ObjectRef> $written
     * @param list<RecordedRow> $rows the rows whose object was saved
     */
    private function afterSave(RunRecord $run, array $written, array $rows): void
    {
        $rowsByKey = [];
        foreach ($rows as $row) {
            $rowsByKey[self::keyOf($row)][] = $row;
        }

        foreach ($written as $ref) {
            $rowsOfObject = [];
            foreach (self::keysOf($ref) as $key) {
                $rowsOfObject = [...$rowsOfObject, ...($rowsByKey[$key] ?? [])];
            }
            if ($rowsOfObject === [] || $ref->id === null) {
                continue;
            }

            try {
                $object = $this->objects->getById($ref->id, ['force' => true]);
                foreach ($object === null ? [] : $rowsOfObject as $row) {
                    $this->eventDispatcher->dispatch(new PostSaveEvent(RowImporter::CONFIG_NAME, $row->data, $object));
                }
            } catch (\Throwable $throwable) { // NOSONAR php:S2221 the object is saved, whatever a listener does after that
                $this->log($run, sprintf('a listener failed after object %d was saved: %s', $ref->id, $throwable->getMessage()));
            }
        }
    }

    /**
     * The import's words for each problem, by the row of the file it is about; a problem no row shows is a notice.
     *
     * @param list<MergeProblem> $problems
     *
     * @return array{0: array<int, string>, 1: string|null} per row number, why it cannot be saved; and the notice
     */
    private function rowProblems(RunRecord $run, array $problems, User $user): array
    {
        $rowsByKey = [];
        foreach ($this->runs->recorded($run) as $row) {
            $rowsByKey[self::keyOf($row)][] = $row;
        }

        $messages = [];
        $notices = [];
        foreach ($problems as $problem) {
            $this->log($run, sprintf(
                'merge problem %s%s: %s',
                $problem->code->value,
                $problem->field === null ? '' : ' at ' . $problem->field,
                $problem->reason
            ));
            $rows = [];
            foreach ($problem->object === null ? [] : self::keysOf($problem->object) as $key) {
                $rows = [...$rows, ...($rowsByKey[$key] ?? [])];
            }
            if ($rows === []) {
                $notices[$problem->code->value] = $this->messages->mergeNotice($problem->code->value, $user);

                continue;
            }
            foreach ($rows as $row) {
                $messages[$row->row] ??= $this->messages->mergeProblem($problem->code->value, $row, $user);
            }
        }

        return [$messages, $notices === [] ? null : implode("\n", $notices)];
    }

    /**
     * @return list<RowFailure>
     */
    private function storedFailures(RunRecord $run): array
    {
        $stored = $this->files->readFailedRows($run->userId, $run->fileId);
        $failed = $stored === null ? null : json_decode($stored, true);
        if (!is_array($failed) || !is_array($failed['rows'] ?? null)) {
            return [];
        }

        return array_values(array_map(RowFailure::fromArray(...), $failed['rows']));
    }

    /**
     * @param list<RowFailure> $failures
     */
    private function storeFailures(RunRecord $run, array $failures): void
    {
        $this->files->storeFailedRows($run->userId, $run->fileId, (string) json_encode([
            'columns' => $run->columns,
            'rows' => array_map(static fn (RowFailure $failure): array => $failure->toArray(), $failures),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<RowFailure> $failures
     *
     * @return list<RowFailure> one per row, the later one kept, by their row
     */
    private static function byRow(array $failures): array
    {
        $byRow = [];
        foreach ($failures as $failure) {
            $byRow[$failure->row] = $failure;
        }
        ksort($byRow);

        return array_values($byRow);
    }

    private static function refOf(RecordedRow $row): ObjectRef
    {
        return $row->token !== null ? ObjectRef::token($row->token) : ObjectRef::of((int) $row->objectId);
    }

    private static function keyOf(RecordedRow $row): string
    {
        return $row->token !== null ? 'token:' . $row->token : 'id:' . $row->objectId;
    }

    /**
     * @return list<string> a created object is known by its token and its new id
     */
    private static function keysOf(ObjectRef $object): array
    {
        return array_values(array_filter([
            $object->token === null ? null : 'token:' . $object->token,
            $object->id === null ? null : 'id:' . $object->id,
        ]));
    }

    private function branches(): BranchServiceInterface
    {
        return $this->branches ?? throw new \LogicException('Change Control is not installed.');
    }

    private function log(RunRecord $run, string $message): void
    {
        $this->logger->error(sprintf('Ad hoc import of %s: %s', $run->fileId, $message));
    }
}
