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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataHubBundle\Installer as DataHubInstaller;
use Pimcore\Bundle\DataImporterBundle\Installer;
use Pimcore\Db;
use Pimcore\Model\User\Permission\Definition;

class AdHocImportInstallerTest extends Unit
{
    private const RIGHTS = [Installer::AD_HOC_IMPORT_PERMISSION, Installer::DATAHUB_ADAPTER_PERMISSION];

    protected $tester;

    protected function _before(): void
    {
        $this->removeRights();
    }

    protected function _after(): void
    {
        $this->removeRights();
    }

    public function testAFreshInstallCreatesTheRights(): void
    {
        $this->assertNull(Definition::getByKey(Installer::AD_HOC_IMPORT_PERMISSION));

        /** @var Installer $installer */
        $installer = \Pimcore::getContainer()->get(Installer::class);
        $installer->install();

        foreach (self::RIGHTS as $right) {
            $definition = Definition::getByKey($right);
            $this->assertNotNull($definition, $right);
            $this->assertSame(DataHubInstaller::DATAHUB_PERMISSION_CATEGORY, $definition->getCategory());
        }
        $this->assertTrue($installer->isInstalled());
    }

    private function removeRights(): void
    {
        Db::get()->executeStatement(
            'DELETE FROM users_permission_definitions WHERE `key` IN (?, ?)',
            self::RIGHTS
        );
    }
}
