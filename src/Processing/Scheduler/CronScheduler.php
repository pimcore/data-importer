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

use Cron\CronExpression;
use DateTime;

class CronScheduler implements SchedulerInterface
{
    const NAME = 'cron';

    private string $cronDefinition;
    private DateTime $modifiedAt;

    public function __construct(string $cronDefinition, DateTime $modifiedAt)
    {
        $this->cronDefinition = $cronDefinition;
        $this->modifiedAt = $modifiedAt;
    }

    public function isExecutable(?DateTime $executedAt): bool
    {
        $cron = new CronExpression($this->cronDefinition);
        $startAt = $executedAt ?: $this->modifiedAt;

        $nextRun = $cron->getNextRunDate($startAt);
        $now = new DateTime();

        return $nextRun < $now;
    }
}
