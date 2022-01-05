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
use Doctrine\DBAL\Exception\TableNotFoundException;
use Pimcore\Db;

class QueueService
{
    const QUEUE_TABLE_NAME = 'bundle_data_hub_data_importer_queue';

    /**
     * @return Db\Connection|Db\ConnectionInterface
     */
    protected function getDb()
    {
        return Db::get();
    }

    protected function getCurrentQueueTableOperationTime(): int
    {
        /** @var Carbon $carbonNow */
        $carbonNow = Carbon::now();

        return (int)($carbonNow->getTimestamp() . str_pad((string)$carbonNow->milli, 3, '0'));
    }

    /**
     * @param string $configName
     * @param string $executionType
     * @param string $jobType
     * @param string $data
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function addItemToQueue(string $configName, string $executionType, string $jobType, string $data): void
    {
        $db = $this->getDb();
        try {
            $db->executeQuery(sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE timestamp = VALUES(timestamp)',
                self::QUEUE_TABLE_NAME,
                implode(',', ['timestamp', 'configName', 'data', 'executionType', 'jobType']),
                implode(',', [
                    $this->getCurrentQueueTableOperationTime(),
                    $db->quote($configName),
                    $db->quote($data),
                    $db->quote($executionType),
                    $db->quote($jobType)
                ])
            ));
        } catch (TableNotFoundException $exception) {
            $this->createQueueTableIfNotExisting(function () use ($configName, $executionType, $jobType, $data) {
                $this->addItemToQueue($configName, $executionType, $jobType, $data);
            });
        }
    }

    protected function createQueueTableIfNotExisting(\Closure $callable = null)
    {
        $this->getDb()->executeQuery(sprintf('CREATE TABLE IF NOT EXISTS %s (
            id bigint AUTO_INCREMENT,
            timestamp bigint NULL,
            configName varchar(80) NULL,
            `data` TEXT null,
            executionType varchar(20) NULL,
            jobType varchar(20) NULL,
            PRIMARY KEY (id),
            KEY `bundle_index_queue_configName_index` (`configName`),
            KEY `bundle_index_queue_configName_index_executionType` (`configName`, `executionType`))
        ', self::QUEUE_TABLE_NAME));

        if ($callable) {
            return $callable();
        }
    }

    /**
     * @param string $executionType
     * @param int $limit
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAllQueueEntryIds(string $executionType, $limit = 100000): array
    {
        try {
            $results = $this->getDb()->fetchCol(
                sprintf('SELECT id FROM %s WHERE executionType = ?', self::QUEUE_TABLE_NAME),
                [$executionType]
            );

            return $results ?? [];
        } catch (TableNotFoundException $exception) {
            return $this->createQueueTableIfNotExisting(function () use ($executionType, $limit) {
                $this->getAllQueueEntryIds($executionType, $limit);
            });
        }
    }

    /**
     * @param int $id
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getQueueEntryById(int $id): array
    {
        try {
            $result = $this->getDb()->fetchRow(
                sprintf('SELECT * FROM %s WHERE id = ?', self::QUEUE_TABLE_NAME),
                [$id]
            );

            return is_array($result) ? $result : [];
        } catch (TableNotFoundException $exception) {
            return $this->createQueueTableIfNotExisting(function () use ($id) {
                $this->getQueueEntryById($id);
            });
        }
    }

    /**
     * @param string $configName
     *
     * @return int
     */
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
     * @throws \Doctrine\DBAL\DBALException
     */
    public function markQueueEntryAsProcessed($id)
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
     * @throws \Doctrine\DBAL\DBALException
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
