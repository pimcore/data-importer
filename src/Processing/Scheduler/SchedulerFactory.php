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
use Pimcore\Bundle\DataImporterBundle\Processing\Scheduler\Exception\InvalidScheduleException;

class SchedulerFactory
{
    /**
     * @throws InvalidScheduleException
     */
    public static function create(array $config): SchedulerInterface
    {
        $scheduleType = $config['executionConfig']['scheduleType'] ?? CronScheduler::NAME;
        $modifiedAt = date_create()->setTimestamp($config['general']['modificationDate']);

        if ($scheduleType === JobScheduler::NAME) {
            if (empty($config['executionConfig']['scheduledAt'])) {
                throw new InvalidScheduleException('No scheduled date/time');
            }

            $scheduledAt = DateTime::createFromFormat('d-m-Y H:i', $config['executionConfig']['scheduledAt']);

            return new JobScheduler($scheduledAt, $modifiedAt);
        }

        if (empty($config['executionConfig']['cronDefinition'])) {
            throw new InvalidScheduleException('No cron definition provided');
        }

        return new CronScheduler($config['executionConfig']['cronDefinition'], $modifiedAt);
    }
}
