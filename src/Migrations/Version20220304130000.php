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
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Migrations\BundleAwareMigration;

/**
 * @internal
 */
final class Version20220304130000 extends BundleAwareMigration
{
    public function up(Schema $schema): void
    {
        if ($schema->hasTable(QueueService::QUEUE_TABLE_NAME)) {
            $queueTable = $schema->getTable(QueueService::QUEUE_TABLE_NAME);
            $queueTable->addColumn('dispatched', 'bigint', ['notnull' => false, 'default' => null]);
            $queueTable->addColumn('workerId', 'string', ['notnull' => false, 'default' => null, 'length' => 13]);
            $queueTable->addIndex(['executionType', 'workerId'], 'bundle_index_queue_executiontype_workerId');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable(QueueService::QUEUE_TABLE_NAME)) {
            $queueTable = $schema->getTable(QueueService::QUEUE_TABLE_NAME);
            $queueTable->dropColumn('dispatched');
            $queueTable->dropColumn('workerId');
            $queueTable->dropIndex('bundle_index_queue_executiontype_workerId');
        }
    }

    protected function getBundleName(): string
    {
        return 'PimcoreDataImporterBundle';
    }
}
