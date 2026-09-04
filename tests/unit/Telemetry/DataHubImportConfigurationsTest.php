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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Telemetry;

use function array_key_first;
use Codeception\Test\Unit;
use function json_encode;
use Pimcore\Bundle\DataHubBundle\Telemetry\DataHubConfigurationUsage;
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataHubImportConfigurations;
use Pimcore\Model\Tool\SettingsStore;
use Symfony\Component\Yaml\Yaml;
use function time;
use function uniqid;

/**
 * The adapter over Data Hub's shared configuration read, exercised against the real store rather than a
 * double: an import configuration is seeded exactly as Data Hub's own Dao stores it, read back through
 * {@see \Pimcore\Bundle\DataHubBundle\Configuration::getList()}, and the adapter's answer checked.
 *
 * This is what pins the adapter type. The seeded configuration carries the type this bundle registers with
 * Data Hub - read from the registration itself - and is the only active configuration in the store, so the
 * adapter reads true only if it asks Data Hub for exactly that type. A typo or drift in
 * {@see DataHubImportConfigurations} fails this test while the provider tests, which mock the seam, would
 * still pass.
 *
 * One store-backed case, deliberately: Data Hub's Dao memoises the configuration list for the lifetime of
 * the process and offers no reset, so a second listing would still see the first one's store. Whether an
 * inactive configuration or another adapter's configuration counts is decided in
 * {@see DataHubConfigurationUsage::hasActiveOfType()}, not here.
 *
 * DB-backed: the unit suite connects the database. The seeded entry carries a unique name and is removed
 * again in {@see _after()}.
 */
class DataHubImportConfigurationsTest extends Unit
{
    private const SETTINGS_STORE_SCOPE = 'pimcore_data_hub';

    private const REGISTRATION = __DIR__ . '/../../../src/Resources/config/pimcore/config.yml';

    private ?string $seeded = null;

    protected function _after(): void
    {
        if ($this->seeded !== null) {
            SettingsStore::delete($this->seeded, self::SETTINGS_STORE_SCOPE);
            $this->seeded = null;
        }
    }

    public function testAnActiveImportConfigurationIsUsed(): void
    {
        $this->seedActiveConfiguration($this->registeredAdapterType());

        $adapter = new DataHubImportConfigurations(new DataHubConfigurationUsage());

        $this->assertTrue($adapter->hasActive());
    }

    /**
     * The seed above is built from the registration; this pins the registration itself to the identifier
     * Data Hub knows this bundle by.
     */
    public function testThisBundleRegistersTheImporterTypeWithDataHub(): void
    {
        $this->assertSame('dataImporterDataObject', $this->registeredAdapterType());
    }

    /**
     * The one adapter type under `pimcore_data_hub.supported_types` in this bundle's own configuration.
     */
    private function registeredAdapterType(): string
    {
        $types = Yaml::parseFile(self::REGISTRATION)['pimcore_data_hub']['supported_types'];

        $this->assertCount(1, $types, 'this bundle is expected to register exactly one Data Hub adapter type');

        return (string) array_key_first($types);
    }

    /**
     * Stores an active configuration the way Data Hub's Dao does: the configuration array itself, under
     * its name, in the `pimcore_data_hub` settings-store scope. Only `general` matters to the read under
     * test.
     */
    private function seedActiveConfiguration(string $type): void
    {
        $name = uniqid('telemetry_test_');
        $now = time();
        $data = [
            'general' => [
                'active' => true,
                'type' => $type,
                'name' => $name,
                'path' => '',
                'group' => '',
                'createDate' => $now,
                'modificationDate' => $now,
            ],
        ];

        SettingsStore::set(
            $name,
            json_encode($data, JSON_THROW_ON_ERROR),
            SettingsStore::TYPE_STRING,
            self::SETTINGS_STORE_SCOPE
        );
        $this->seeded = $name;
    }
}
