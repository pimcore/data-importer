<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\Processing\Cron;

use Cron\CronExpression;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Pimcore\Db;

class CronExecutionService
{
    /**
     * @var Db\Connection|Db\ConnectionInterface
     */
    protected $db;

    const EXECUTION_STORAGE_TABLE_NAME = 'bundle_data_hub_batch_import_last_cron_execution';

    public function __construct(Db\ConnectionInterface $connection)
    {
        $this->db = $connection;
    }

    protected function createTableIfNotExisting(\Closure $callable = null)
    {
        $this->db->executeQuery(sprintf('CREATE TABLE IF NOT EXISTS %s (
            configName varchar(50) NOT NULL,
            lastExecutionDate int(11),
            PRIMARY KEY (configName))
        ', self::EXECUTION_STORAGE_TABLE_NAME));

        if ($callable) {
            return $callable();
        }
    }

    protected function getLastExecution($configName): \DateTime
    {
        try {
            $timestamp = $this->db->fetchOne(
                    sprintf('SELECT lastExecutionDate FROM %s WHERE configName = ?', self::EXECUTION_STORAGE_TABLE_NAME),
                    [$configName]
                ) ?? time();

            return date_create()->setTimestamp($timestamp);
        } catch (TableNotFoundException $exception) {
            return $this->createTableIfNotExisting(function () use ($configName) {
                $this->getLastExecution($configName);
            });
        }
    }

    /**
     * @param string $configName
     * @param string $cronDefinition
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function getNextExecutionInPast(string $configName, string $cronDefinition): bool
    {
        $cron = new CronExpression($cronDefinition);
        $lastExecution = $this->getLastExecution($configName);
        $nextRun = $cron->getNextRunDate($lastExecution);

        $now = new \DateTime();

        return $nextRun < $now;
    }

    /**
     * @param string $configName
     * @param \DateTime $executionTimestamp
     *
     * @return mixed
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateExecutionTimestamp(string $configName, \DateTime $executionTimestamp)
    {
        try {
            $this->db->executeQuery(
                sprintf('INSERT INTO %s (configName, lastExecutionDate) VALUES (?, ?) ON DUPLICATE KEY UPDATE lastExecutionDate = ?', self::EXECUTION_STORAGE_TABLE_NAME),
                [$configName, $executionTimestamp->getTimestamp(), $executionTimestamp->getTimestamp()]
            );
        } catch (TableNotFoundException $exception) {
            return $this->createTableIfNotExisting(function () use ($configName, $executionTimestamp) {
                $this->updateExecutionTimestamp($configName, $executionTimestamp);
            });
        }
    }

    public function cleanup(string $configName)
    {
        try {
            $this->db->executeQuery(
                sprintf('DELETE FROM %s WHERE configName = ?', self::EXECUTION_STORAGE_TABLE_NAME),
                [$configName]
            );
        } catch (TableNotFoundException $exception) {
            return $this->createTableIfNotExisting();
        }
    }
}
