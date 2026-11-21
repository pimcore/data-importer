<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

declare(strict_types=1);

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

/**
 * @internal
 */
final class Version20211201173215 extends BundleAwareMigration
{
    private const ORIGINAL_NAME = 'bundle_data_hub_data_importer_last_cron_execution';

    private const TARGET_NAME = 'bundle_data_hub_data_importer_last_execution';

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
