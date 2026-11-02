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

namespace Pimcore\Bundle\DataImporterBundle\Processing\Scheduler;

use DateTime;

/**
 * @internal
 */
final class JobScheduler implements SchedulerInterface
{
    public const NAME = 'job';

    public function __construct(
        private readonly DateTime $scheduledAt,
        private readonly DateTime $modifiedAt,
    ) {
    }

    public function isExecutable(?DateTime $executedAt): bool
    {
        $now = new DateTime();

        $hasExecutedInPast = $executedAt && $this->scheduledAt <= $executedAt;
        $isTimeToExecute = $now >= $this->scheduledAt;
        $isModifiedBeforeSchedule = $this->modifiedAt <= $this->scheduledAt;

        if ($isTimeToExecute && $isModifiedBeforeSchedule && !$hasExecutedInPast) {
            return true;
        }

        return false;
    }
}
