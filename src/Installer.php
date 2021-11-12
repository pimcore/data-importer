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

use Pimcore\Bundle\DataImporterBundle\Migrations\Version20210305134111;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Pimcore\Model\Tool\SettingsStore;

class Installer extends SettingsStoreAwareInstaller
{
    const DATAHUB_ADAPTER_PERMISSION = 'plugin_datahub_adapter_dataImporterDataObject';

    public function needsReloadAfterInstall(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function install()
    {
        // create backend permission
        \Pimcore\Model\User\Permission\Definition::create(self::DATAHUB_ADAPTER_PERMISSION);

        parent::install();

        return true;
    }

    public function isInstalled()
    {
        // When switching to SettingsStoreAwareInstaller, we need to explicitly mark this bundle installed,
        // if Settingstore entry doesn't exists and datahub permission is installed
        // e.g. updating from 1.0.* to 1.1.*
        $installEntry = SettingsStore::get($this->getSettingsStoreInstallationId(), 'pimcore');
        if (!$installEntry) {
            $this->markInstalled();

            return true;
        }

        return parent::isInstalled();
    }

    public function getLastMigrationVersionClassName(): ?string
    {
        return Version20210305134111::class;
    }
}
