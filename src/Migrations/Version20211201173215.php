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
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

declare(strict_types=1);

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
use Pimcore\Migrations\BundleAwareMigration;

final class Version20211201173215 extends BundleAwareMigration
{
    protected const ORIGINAL_NAME = 'bundle_data_hub_data_importer_last_cron_execution';
    protected const TARGET_NAME = 'bundle_data_hub_data_importer_last_execution';

    protected function getBundleName(): string
    {
        return 'PimcoreDataImporterBundle';
    }

    public function getDescription(): string
    {
        return 'Rename last execution table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable(self::ORIGINAL_NAME)) {
            $this->addSql(sprintf('RENAME TABLE `%s` TO `%s`', self::ORIGINAL_NAME, self::TARGET_NAME));
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable(self::TARGET_NAME)) {
            $this->addSql(sprintf('RENAME TABLE `%s` TO `%s`', self::TARGET_NAME, self::ORIGINAL_NAME));
        }
    }
}
