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

namespace Pimcore\Bundle\DataImporterBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Pimcore\Migrations\BundleAwareMigration;

class Version20211110174732 extends BundleAwareMigration
{
    protected const DELTA_CACHE_TABLE = 'bundle_data_hub_data_importer_delta_cache';

    protected const LAST_CRON_TABLE = 'bundle_data_hub_data_importer_last_cron_execution';

    protected const IMPORTER_QUEUE_TABLE = 'bundle_data_hub_data_importer_queue';

    protected function getBundleName(): string
    {
        return 'PimcoreDataImporterBundle';
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        if ($schema->hasTable(self::DELTA_CACHE_TABLE)) {
            $this->addSql(sprintf('ALTER TABLE `%s` MODIFY `configName` VARCHAR(80)', self::DELTA_CACHE_TABLE));
        }

        if ($schema->hasTable(self::LAST_CRON_TABLE)) {
            $this->addSql(sprintf('ALTER TABLE `%s` MODIFY `configName` VARCHAR(80)', self::LAST_CRON_TABLE));
        }

        if ($schema->hasTable(self::IMPORTER_QUEUE_TABLE)) {
            $this->addSql(sprintf('ALTER TABLE `%s` MODIFY `configName` VARCHAR(80)', self::IMPORTER_QUEUE_TABLE));
        }
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        if ($schema->hasTable(self::DELTA_CACHE_TABLE)) {
            $this->addSql(sprintf('ALTER TABLE `%s` MODIFY `configName` VARCHAR(50)', self::DELTA_CACHE_TABLE));
        }

        if ($schema->hasTable(self::LAST_CRON_TABLE)) {
            $this->addSql(sprintf('ALTER TABLE `%s` MODIFY `configName` VARCHAR(50)', self::LAST_CRON_TABLE));
        }

        if ($schema->hasTable(self::IMPORTER_QUEUE_TABLE)) {
            $this->addSql(sprintf('ALTER TABLE `%s` MODIFY `configName` VARCHAR(50)', self::IMPORTER_QUEUE_TABLE));
        }
    }
}
