<?php
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
use Pimcore\Bundle\DataHubBundle\Installer as DataHubInstaller;
use Pimcore\Bundle\DataImporterBundle\Installer;
use Pimcore\Migrations\BundleAwareMigration;

/**
 * @internal
 */
final class Version20261106170000 extends BundleAwareMigration
{
    public function getDescription(): string
    {
        return 'Adds the right to import a file ad hoc into a data object folder.';
    }

    public function up(Schema $schema): void
    {
        // IGNORE: a fresh install has created it already
        $this->addSql(
            'INSERT IGNORE INTO users_permission_definitions (`key`, `category`) VALUES (?, ?)',
            [Installer::AD_HOC_IMPORT_PERMISSION, DataHubInstaller::DATAHUB_PERMISSION_CATEGORY]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM users_permission_definitions WHERE `key` = ?',
            [Installer::AD_HOC_IMPORT_PERMISSION]
        );
    }

    protected function getBundleName(): string
    {
        return 'PimcoreDataImporterBundle';
    }
}
