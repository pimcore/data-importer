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

use Pimcore\Bundle\DataImporterBundle\Migrations\Version20211110174732;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;

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
        \Pimcore\Model\User\Permission\Definition::create(self::DATAHUB_ADAPTER_PERMISSION)->setCategory(\Pimcore\Bundle\DataHubBundle\Installer::DATAHUB_PERMISSION_CATEGORY)->save();

        parent::install();

        return true;
    }

    public function getLastMigrationVersionClassName(): ?string
    {
        return Version20211110174732::class;
    }
}
