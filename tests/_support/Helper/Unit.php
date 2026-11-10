<?php
namespace Pimcore\Bundle\DataImporterBundle\Tests\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use Pimcore\Db;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Unit extends Module
{
    private bool $applicationLogTableReady = false;

    /**
     * Creates the application log table the importer writes its own problems to.
     *
     * A data target reached through the real `MappingConfigurationFactory` carries the real
     * `ApplicationLogger`, whose database handler inserts into `application_logs`. That table
     * belongs to `ApplicationLoggerBundle` and is created by that bundle's installer, which the
     * Codeception setup never runs: `DatabaseSetup::createSchema()` installs the core schema only.
     * Without the table every warning a test triggers raises a table-not-found error instead of
     * being logged, which turned the round trip audit's skipped `date` key into a crash.
     */
    public function _before(TestInterface $test): void
    {
        if ($this->applicationLogTableReady) {
            return;
        }

        // the table as Pimcore\Bundle\ApplicationLoggerBundle\Installer creates it
        Db::get()->executeStatement(
            'CREATE TABLE IF NOT EXISTS `application_logs` ('
            . '`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,'
            . '`pid` INT(11) NULL DEFAULT NULL,'
            . '`timestamp` datetime NOT NULL,'
            . '`message` TEXT NULL,'
            . "`priority` ENUM('emergency','alert','critical','error','warning','notice','info','debug')"
            . ' DEFAULT NULL,'
            . '`fileobject` varchar(1024) DEFAULT NULL,'
            . '`info` varchar(1024) DEFAULT NULL,'
            . '`component` varchar(190) DEFAULT NULL,'
            . '`source` varchar(190) DEFAULT NULL,'
            . '`relatedobject` int(11) unsigned DEFAULT NULL,'
            . "`relatedobjecttype` enum('object','document','asset') DEFAULT NULL,"
            . '`maintenanceChecked` tinyint(1) DEFAULT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'KEY `component` (`component`),'
            . 'KEY `timestamp` (`timestamp`),'
            . 'KEY `relatedobject` (`relatedobject`),'
            . 'KEY `priority` (`priority`),'
            . 'KEY `maintenanceChecked` (`maintenanceChecked`)'
            . ') DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci'
        );

        $this->applicationLogTableReady = true;
    }
}
