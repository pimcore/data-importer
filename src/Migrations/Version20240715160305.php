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

namespace Pimcore\Bundle\DataImporterBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Migrations\BundleAwareMigration;

class Version20240715160305 extends BundleAwareMigration
{
    public function up(Schema $schema): void
    {
        if (!$schema->hasTable(QueueService::QUEUE_TABLE_NAME)) {
            return;
        }

        $queueTable = $schema->getTable(QueueService::QUEUE_TABLE_NAME);

        if ($queueTable->hasColumn('userOwner')) {
            return;
        }

        $queueTable->addColumn('userOwner', 'integer', ['notnull' => true, 'default' => 0])->setUnsigned(true);

        if ($queueTable->hasIndex('bundle_index_queue_executiontype_userOwner')) {
            return;
        }

        $queueTable->addIndex(['userOwner'], 'bundle_index_queue_executiontype_userOwner');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable(QueueService::QUEUE_TABLE_NAME)) {
            return;
        }

        $queueTable = $schema->getTable(QueueService::QUEUE_TABLE_NAME);

        if (!$queueTable->hasColumn('userOwner')) {
            return;
        }

        $queueTable->dropColumn('userOwner');

        if (!$queueTable->hasIndex('bundle_index_queue_executiontype_userOwner')) {
            return;
        }

        $queueTable->dropIndex('bundle_index_queue_executiontype_userOwner');
    }

    protected function getBundleName(): string
    {
        return 'PimcoreDataImporterBundle';
    }
}
