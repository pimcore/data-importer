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

namespace Pimcore\Bundle\DataImporterBundle;

use Pimcore\Bundle\DataImporterBundle\Migrations\Version20240715160305;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Pimcore\Model\User\Permission;

class Installer extends SettingsStoreAwareInstaller
{
    const DATAHUB_ADAPTER_PERMISSION = 'plugin_datahub_adapter_dataImporterDataObject';

    public function needsReloadAfterInstall(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function install(): void
    {
        /**
         * The application logger can be deactivated from Pimcore 11 on. But it is necessary for the data-importer,
         * so we have to make sure that it is activated & installed.
         */
        if (\Pimcore\Version::getMajorVersion() >= 11) {
            $appLoggerInstaller = \Pimcore::getContainer()->get(\Pimcore\Bundle\ApplicationLoggerBundle\Installer::class);

            if (!$appLoggerInstaller->isInstalled()) {
                $appLoggerInstaller->install();
            }
        }

        // create backend permission
        Permission\Definition::create(self::DATAHUB_ADAPTER_PERMISSION)
            ->setCategory(\Pimcore\Bundle\DataHubBundle\Installer::DATAHUB_PERMISSION_CATEGORY)
            ->save();

        parent::install();
    }

    public function getLastMigrationVersionClassName(): ?string
    {
        return Version20240715160305::class;
    }
}
