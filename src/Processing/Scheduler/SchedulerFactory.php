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
