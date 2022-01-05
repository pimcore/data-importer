<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Processing\Scheduler;

use DateTime;

class JobScheduler implements SchedulerInterface
{
    const NAME = 'job';

    private DateTime $scheduledAt;
    private DateTime $modifiedAt;

    public function __construct(DateTime $scheduledAt, DateTime $modifiedAt)
    {
        $this->scheduledAt = $scheduledAt;
        $this->modifiedAt = $modifiedAt;
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
