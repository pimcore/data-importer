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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\BranchMergeInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DecisionLock;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Maintenance\TaskInterface;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Looks after the ad hoc imports that go through a branch: a merge no worker follows any more is followed here, and
 * an import nobody decided on within a week is discarded, its branch closed and its file deleted.
 *
 * @internal
 */
final readonly class BranchImportsTask implements TaskInterface
{
    private const int DECISION_SECONDS = 7 * 86400;

    // a worker looks every few seconds; a merge it has not looked at for this long has no worker
    private const int UNFOLLOWED_SECONDS = 60;

    public function __construct(
        private RunStoreInterface $runs,
        private BranchMergeInterface $merges,
        private FileStore $files,
        private UserResolverInterface $users,
        private LockFactory $locks,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        $now = time();
        foreach ($this->runs->inState(RunRecord::MERGING) as $run) {
            if ($now - ($run->checkedAt ?? 0) >= self::UNFOLLOWED_SECONDS) {
                $this->attempt($run, fn () => $this->merges->follow($run->userId, $run->fileId));
            }
        }

        foreach ($this->runs->inState(RunRecord::DECISION) as $run) {
            if (self::overdue($run, $now)) {
                $this->attempt($run, fn () => $this->discardOverdue($run, $now));
            }
        }
    }

    /**
     * The user may be deciding on it right now, or have done so since the list was read.
     */
    private function discardOverdue(RunRecord $listed, int $now): void
    {
        $lock = DecisionLock::of($this->locks, $listed->userId, $listed->fileId);
        if (!$lock->acquire()) {
            return;
        }

        try {
            $run = $this->runs->find($listed->userId, $listed->fileId);
            if ($run !== null && self::overdue($run, $now)) {
                $this->discard($run);
            }
        } finally {
            $lock->release();
        }
    }

    private static function overdue(RunRecord $run, int $now): bool
    {
        return $run->isWaitingForDecision() && $now - ($run->waitingSince ?? 0) >= self::DECISION_SECONDS;
    }

    private function discard(RunRecord $run): void
    {
        $user = $this->users->getById($run->userId);
        if ($user instanceof User) {
            $this->merges->discard($run, $user);
        } else {
            $this->runs->forgetRecorded($run);
        }
        $this->files->delete($run->userId, $run->fileId, $run->type);
        $this->files->deleteRunOutput($run->userId, $run->fileId);
        $this->runs->delete($run->userId, $run->fileId);
    }

    /**
     * @param callable(): void $step
     */
    private function attempt(RunRecord $run, callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 one import must not keep the others waiting
            $this->logger->error(sprintf('Ad hoc import of %s: %s', $run->fileId, $throwable->getMessage()));
        }
    }
}
