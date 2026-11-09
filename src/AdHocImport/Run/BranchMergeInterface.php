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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Model\User;

/**
 * What becomes of a run whose rows went into a Change Control branch: the branch is checked and merged, or the user
 * decides on the rows that cannot be saved.
 *
 * @internal
 */
interface BranchMergeInterface
{
    /**
     * Every row of the run is dealt with: a row that could not be written holds the merge for a decision, else the
     * merge starts.
     *
     * @param list<RowFailure> $failures the rows that could not be written
     */
    public function rowsWritten(RunRecord $run, ?ImportPlan $plan, ?User $user, array $failures): void;

    /**
     * Looks how the run's merge goes, and goes on with what it came to.
     */
    public function follow(int $userId, string $fileId): void;

    /**
     * Takes the rows that cannot be saved out of the branch and merges the others.
     */
    public function mergeLeavingOut(RunRecord $run, User $user): RunRecord;

    /**
     * Closes the run's branch without merging it.
     */
    public function discard(RunRecord $run, User $user): void;
}
