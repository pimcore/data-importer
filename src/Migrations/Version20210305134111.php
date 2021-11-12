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

namespace Pimcore\Bundle\DataImporterBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Pimcore\Bundle\DataImporterBundle\Installer;
use Pimcore\Migrations\BundleAwareMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20210305134111 extends BundleAwareMigration
{

    protected function getBundleName(): string
    {
        return 'PimcoreDataImporterBundle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf("INSERT IGNORE INTO users_permission_definitions (`key`) VALUES('%s');", Installer::DATAHUB_ADAPTER_PERMISSION));
    }

    public function down(Schema $schema): void
    {
        $this->addSql(sprintf("DELETE FROM users_permission_definitions WHERE `key` = '%s'", Installer::DATAHUB_ADAPTER_PERMISSION));
    }
}
