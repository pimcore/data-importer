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

use Codeception\Test\Unit;
use function json_encode;
use Pimcore\Bundle\DataHubBundle\Configuration\Dao;
use Pimcore\Bundle\DataHubBundle\Telemetry\DataHubConfigurationUsage;
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataHubImportConfigurations;
use Pimcore\Model\Tool\SettingsStore;
use ReflectionProperty;
use Symfony\Component\Yaml\Yaml;
use function time;
use function uniqid;

/**
 * The adapter over Data Hub's shared configuration read, exercised against the real store rather than a
 * double: a configuration is seeded exactly as Data Hub's own Dao stores it, read back through
 * {@see \Pimcore\Bundle\DataHubBundle\Configuration::getList()}, and the adapter's answer checked.
 *
 * This is what pins the adapter type. {@see DataHubImportConfigurations} names `dataImporterDataObject`,
 * and only a configuration stored under exactly that type may make it true; a typo or drift there fails
 * these tests, while the provider tests, which mock the seam, would still pass.
 *
 * DB-backed: the unit suite connects the database. Every seeded entry carries a unique name and is removed
 * again in {@see _after()}.
 */
class DataHubImportConfigurationsTest extends Unit
{
    private const SETTINGS_STORE_SCOPE = 'pimcore_data_hub';

    private const IMPORTER_TYPE = 'dataImporterDataObject';

    /**
     * @var list<string>
     */
    private array $seeded = [];

    protected function _before(): void
    {
        $this->forgetCachedList();
    }

    protected function _after(): void
    {
        foreach ($this->seeded as $name) {
            SettingsStore::delete($name, self::SETTINGS_STORE_SCOPE);
        }
        $this->seeded = [];
        $this->forgetCachedList();
    }

    public function testAnActiveImportConfigurationIsUsed(): void
    {
        $this->seed(self::IMPORTER_TYPE, active: true);

        $this->assertTrue($this->adapter()->hasActive());
    }

    /**
     * Active rather than merely existing: a disabled import is one somebody built and then switched off.
     */
    public function testAnInactiveImportConfigurationIsNotUsed(): void
    {
        $this->seed(self::IMPORTER_TYPE, active: false);

        $used = $this->adapter()->hasActive();

        $this->assertFalse($used);
        $this->assertNotNull($used);
    }

    /**
     * Data Hub keeps every adapter's configurations in one store; another adapter's active configuration
     * is not an import.
     */
    public function testAnActiveConfigurationOfAnotherAdapterIsNotAnImport(): void
    {
        $this->seed('graphql', active: true);

        $this->assertFalse($this->adapter()->hasActive());
    }

    /**
     * The type these tests seed is the one this bundle registers with Data Hub, so the adapter, the tests
     * and the registration cannot drift apart unnoticed.
     */
    public function testTheImporterTypeIsTheOneRegisteredWithDataHub(): void
    {
        $registered = Yaml::parseFile(__DIR__ . '/../../../src/Resources/config/pimcore/config.yml');

        $this->assertArrayHasKey(self::IMPORTER_TYPE, $registered['pimcore_data_hub']['supported_types']);
    }

    private function adapter(): DataHubImportConfigurations
    {
        // A fresh instance each time: DataHubConfigurationUsage memoises its read for the lifetime of the
        // service, which is one snapshot run.
        return new DataHubImportConfigurations(new DataHubConfigurationUsage());
    }

    /**
     * Stores a configuration the way Data Hub's Dao does: the configuration array itself, under its name,
     * in the `pimcore_data_hub` settings-store scope. Only `general` matters to the read under test.
     */
    private function seed(string $type, bool $active): void
    {
        $name = uniqid('telemetry_test_');
        $now = time();
        $data = [
            'general' => [
                'active' => $active,
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
        $this->seeded[] = $name;
    }

    /**
     * Data Hub's Dao memoises the configuration list in a private static for the lifetime of the process -
     * right for one snapshot run, wrong for a test that seeds between cases.
     */
    private function forgetCachedList(): void
    {
        (new ReflectionProperty(Dao::class, '_config'))->setValue(null, null);
    }
}
