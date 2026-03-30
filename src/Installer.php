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

namespace Pimcore\Bundle\DataImporterBundle;

use Pimcore\Bundle\DataImporterBundle\Migrations\Version20240715160305;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Pimcore\Model\User\Permission;

/**
 * @internal
 */
final class Installer extends SettingsStoreAwareInstaller
{
    public const DATAHUB_ADAPTER_PERMISSION = 'plugin_datahub_adapter_dataImporterDataObject';

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function install(): void
    {
        $appLoggerInstaller = \Pimcore::getContainer()?->get(\Pimcore\Bundle\ApplicationLoggerBundle\Installer::class);

        if ($appLoggerInstaller && !$appLoggerInstaller->isInstalled()) {
            $appLoggerInstaller->install();
        }

        // create backend permission
        Permission\Definition::create(self::DATAHUB_ADAPTER_PERMISSION)
            ->setCategory(\Pimcore\Bundle\DataHubBundle\Installer::DATAHUB_PERMISSION_CATEGORY)
            ->save();

        parent::install();
    }

    public function getLastMigrationVersionClassName(): string
    {
        return Version20240715160305::class;
    }
}
