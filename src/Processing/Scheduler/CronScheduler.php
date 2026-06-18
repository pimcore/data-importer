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
