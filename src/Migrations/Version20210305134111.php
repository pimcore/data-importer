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
use Pimcore\Bundle\DataImporterBundle\Installer;
use Pimcore\Migrations\BundleAwareMigration;
use Pimcore\Model\Tool\SettingsStore;

class Version20210305134111 extends BundleAwareMigration
{
    protected function getBundleName(): string
    {
        return 'PimcoreDataImporterBundle';
    }

    protected function checkBundleInstalled(): bool
    {
        //need to always return true here, as the migration is setting the bundle installed
        return true;
    }

    public function up(Schema $schema): void
    {
        SettingsStore::set('BUNDLE_INSTALLED__Pimcore\\Bundle\\DataImporterBundle\\PimcoreDataImporterBundle', true, 'bool', 'pimcore');
        $this->addSql(sprintf("INSERT IGNORE INTO users_permission_definitions (`key`, `category`) VALUES('%s', '%s');", Installer::DATAHUB_ADAPTER_PERMISSION, \Pimcore\Bundle\DataHubBundle\Installer::DATAHUB_PERMISSION_CATEGORY));
    }

    public function down(Schema $schema): void
    {
    }
}
