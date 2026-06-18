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

/**
 * @internal
 */
final class CronScheduler implements SchedulerInterface
{
    public const NAME = 'cron';

    public function __construct(
        private readonly string $cronDefinition,
        private readonly DateTime $modifiedAt,
    ) {
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
