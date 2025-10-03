<?php

declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Migrations\BundleAwareMigration;

final class Version20251003105903 extends BundleAwareMigration
{
    public function getDescription(): string
    {
        return 'Changes type of data column in data importer table from TEXT to MEDIUMTEXT to accommodate larger data entries.';
    }

    public function up(Schema $schema): void
    {
        $queueTableName = QueueService::QUEUE_TABLE_NAME;
        $this->addSql("
            ALTER TABLE $queueTableName
            MODIFY COLUMN `data` MEDIUMTEXT
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci,
            ALGORITHM=COPY;
        ");
    }

    public function down(Schema $schema): void
    {
        $queueTableName = QueueService::QUEUE_TABLE_NAME;
        $this->addSql("
            ALTER TABLE $queueTableName
            MODIFY COLUMN `data` TEXT
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci,
            ALGORITHM=COPY;
        ");
    }

    protected function getBundleName(): string
    {
        return 'PimcoreDataImporterBundle';
    }
}
