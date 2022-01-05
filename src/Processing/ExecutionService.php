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

namespace Pimcore\Bundle\DataImporterBundle\Processing;

use DateTime;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Pimcore\Db;

class ExecutionService
{
    const EXECUTION_STORAGE_TABLE_NAME = 'bundle_data_hub_data_importer_last_cron_execution';

    /**
     * @return Db\Connection|Db\ConnectionInterface
     */
    protected function getDb()
    {
        return Db::get();
    }

    protected function createTableIfNotExisting()
    {
        $this->getDb()->executeQuery(sprintf('CREATE TABLE IF NOT EXISTS %s (
            configName varchar(80) NOT NULL,
            lastExecutionDate int(11),
            PRIMARY KEY (configName))
        ', self::EXECUTION_STORAGE_TABLE_NAME));
    }

    public function getLastExecution($configName): ?DateTime
    {
        try {
            $timestamp = $this->getDb()->fetchOne(
                sprintf('SELECT lastExecutionDate FROM %s WHERE configName = ?',
                    self::EXECUTION_STORAGE_TABLE_NAME),
                [$configName]
            );

            return $timestamp ? date_create()->setTimestamp($timestamp) : null;
        } catch (TableNotFoundException $exception) {
            $this->createTableIfNotExisting();

            return $this->getLastExecution($configName);
        }
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateExecutionTimestamp(string $configName, DateTime $executionTimestamp)
    {
        try {
            $this->getDb()->executeQuery(
                sprintf('INSERT INTO %s (configName, lastExecutionDate) VALUES (?, ?) ON DUPLICATE KEY UPDATE lastExecutionDate = ?',
                    self::EXECUTION_STORAGE_TABLE_NAME),
                [$configName, $executionTimestamp->getTimestamp(), $executionTimestamp->getTimestamp()]
            );
        } catch (TableNotFoundException $exception) {
            $this->createTableIfNotExisting();
            $this->updateExecutionTimestamp($configName, $executionTimestamp);
        }
    }

    public function initExecution(string $configName)
    {
        try {
            $timestamp = $this->getDb()->fetchOne(
                sprintf('SELECT lastExecutionDate FROM %s WHERE configName = ?', self::EXECUTION_STORAGE_TABLE_NAME),
                [$configName]
            );
        } catch (TableNotFoundException $exception) {
            $timestamp = false;
        }

        if ($timestamp === false) {
            $this->updateExecutionTimestamp($configName, new \DateTime());
        }
    }

    public function cleanup(string $configName)
    {
        try {
            $this->getDb()->executeQuery(
                sprintf('DELETE FROM %s WHERE configName = ?', self::EXECUTION_STORAGE_TABLE_NAME),
                [$configName]
            );
        } catch (TableNotFoundException $exception) {
            $this->createTableIfNotExisting();
        }
    }
}
