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

namespace Pimcore\Bundle\DataImporterBundle\Queue;

use Carbon\Carbon;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Pimcore\Db;

class QueueService
{
    const QUEUE_TABLE_NAME = 'bundle_data_hub_data_importer_queue';

    /**
     * @return Connection
     */
    protected function getDb()
    {
        /** @var Connection $db */
        $db = Db::get();

        return $db;
    }

    protected function getCurrentQueueTableOperationTime(): int
    {
        $carbonNow = Carbon::now();

        return (int)($carbonNow->getTimestamp() . str_pad((string)$carbonNow->milli, 3, '0'));
    }

    /**
     * @param string $configName
     * @param string $executionType
     * @param string $jobType
     * @param string $data
     *
     * @throws Exception
     */
    public function addItemToQueue(string $configName, string $executionType, string $jobType, string $data, int $userOwner = 0): void
    {
        $db = $this->getDb();
        try {
            $db->executeQuery(sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE timestamp = VALUES(timestamp)',
                self::QUEUE_TABLE_NAME,
                implode(',', ['timestamp', 'configName', 'data', 'executionType', 'jobType', 'userOwner']),
                implode(',', [
                    $this->getCurrentQueueTableOperationTime(),
                    $db->quote($configName),
                    $db->quote($data),
                    $db->quote($executionType),
                    $db->quote($jobType),
                    $userOwner
                ])
            ));
        } catch (TableNotFoundException $exception) {
            $this->createQueueTableIfNotExisting(function () use ($configName, $executionType, $jobType, $data, $userOwner) {
                $this->addItemToQueue($configName, $executionType, $jobType, $data, $userOwner);
            });
        }
    }

    /**
     * @param \Closure|null $callable
     *
     * @return mixed|null
     *
     * @throws Exception
     */
    protected function createQueueTableIfNotExisting(\Closure $callable = null)
    {
        $this->getDb()->executeQuery(sprintf('CREATE TABLE IF NOT EXISTS %s (
            id bigint AUTO_INCREMENT,
            timestamp bigint NULL,
            userOwner int unsigned NOT NULL DEFAULT 0,

            configName varchar(80) NULL,
            `data` TEXT null,
            executionType varchar(20) NULL,
            jobType varchar(20) NULL,
            dispatched bigint NULL,
            workerId varchar(13) NULL,
            PRIMARY KEY (id),
            KEY `bundle_index_queue_configName_index` (`configName`),
            KEY `bundle_index_queue_executiontype_workerId` (`executionType`, `workerId`),
            KEY `bundle_index_queue_configName_index_executionType` (`configName`, `executionType`),
            KEY `bundle_index_queue_executiontype_userOwner` (`userOwner`))
        ', self::QUEUE_TABLE_NAME));

        if ($callable) {
            return $callable();
        }

        return null;
    }

    /**
     * @param string $executionType
     * @param int $limit
     * @param bool $dispatch
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\Driver\Exception|Exception
     */
    public function getAllQueueEntryIds(string $executionType, int $limit = 100000, bool $dispatch = false): array
    {
        try {
            if ($dispatch === true) {
                $dispatchId = time();
                $workerId = uniqid();

                $this->getDb()->executeQuery('UPDATE ' . self::QUEUE_TABLE_NAME . ' SET dispatched = ?, workerId = ? WHERE executionType = ? AND (ISNULL(dispatched) OR dispatched < ?) LIMIT ' . intval($limit),
                    [$dispatchId, $workerId, $executionType, $dispatchId - 3000]);

                $results = $this->getDb()->fetchFirstColumn(
                    sprintf('SELECT id FROM %s WHERE executionType = ? AND workerId = ?', self::QUEUE_TABLE_NAME),
                    [$executionType, $workerId]
                );
            } else {
                $results = $this->getDb()->fetchFirstColumn(
                    sprintf('SELECT id FROM %s WHERE executionType = ?', self::QUEUE_TABLE_NAME),
                    [$executionType]
                );
            }

            return $results ?? []; // @phpstan-ignore-line
        } catch (TableNotFoundException $exception) {
            return $this->createQueueTableIfNotExisting(function () use ($executionType, $limit) {
                return $this->getAllQueueEntryIds($executionType, $limit);
            });
        }
    }

    /**
     * @param int $id
     *
     * @return array
     *
     * @throws Exception
     */
    public function getQueueEntryById(int $id): array
    {
        try {
            $result = $this->getDb()->fetchAssociative(
                sprintf('SELECT * FROM %s WHERE id = ?', self::QUEUE_TABLE_NAME),
                [$id]
            );

            return is_array($result) ? $result : [];
        } catch (TableNotFoundException $exception) {
            return $this->createQueueTableIfNotExisting(function () use ($id) {
                return $this->getQueueEntryById($id);
            });
        }
    }

    public function getQueueItemCount(string $configName): int
    {
        try {
            return $this->getDb()->fetchOne(
                sprintf('SELECT count(*) as count FROM %s WHERE configName = ?', self::QUEUE_TABLE_NAME),
                    [$configName]
            ) ?? 0;
        } catch (TableNotFoundException $exception) {
            return $this->createQueueTableIfNotExisting(function () use ($configName) {
                return $this->getQueueItemCount($configName);
            });
        }
    }

    /**
     * @param int $id
     *
     * @throws Exception
     */
    public function markQueueEntryAsProcessed($id): void
    {
        try {
            $this->getDb()->executeQuery(
                sprintf('DELETE FROM %s WHERE id = ?', self::QUEUE_TABLE_NAME),
                [$id]
            );
        } catch (TableNotFoundException $exception) {
            $this->createQueueTableIfNotExisting();
        }
    }

    /**
     * @param string $configName
     *
     * @throws Exception
     */
    public function cleanupQueueItems(string $configName): void
    {
        try {
            $this->getDb()->executeQuery(
                sprintf('DELETE FROM %s WHERE configName = ?', self::QUEUE_TABLE_NAME),
                [$configName]
            );
        } catch (TableNotFoundException $exception) {
            $this->createQueueTableIfNotExisting();
        }
    }
}
