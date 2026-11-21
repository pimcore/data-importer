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
use Doctrine\DBAL\ConnectionException;
use function json_encode;
use Pimcore\Bundle\DataHubBundle\Configuration;
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
 *
 * The kernel-less cases below feed the adapter an injected listing instead and check what it does with
 * the configurations: which pass, what part of them leaves, and that an unreadable listing reads as null.
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

    // --- kernel-less cases through an injected listing (no store) ---

    public function testAnUnreadableListingIsUnknownNotEmpty(): void
    {
        $listing = static function (): array {
            throw new ConnectionException('data hub listing unavailable');
        };
        $reader = new DataHubImportConfigurations(new DataHubConfigurationUsage(), $listing);

        $this->assertNull($reader->activeExecutionConfigs());
    }

    /**
     * Only active import configurations count, and only their execution config leaves the reader.
     */
    public function testReturnsTheExecutionConfigOfActiveImportConfigurations(): void
    {
        $listing = fn (): array => [
            $this->configuration('dataImporterDataObject', true, [
                'general' => ['name' => 'secret'],
                'executionConfig' => ['cronDefinition' => '0 * * * *'],
            ]),
            $this->configuration('dataImporterDataObject', false, [
                'executionConfig' => ['cronDefinition' => '* * * * *'],
            ]),
            $this->configuration('fileExport', true, [
                'executionConfig' => ['cronDefinitionFullExport' => '0 0 * * *'],
            ]),
            $this->configuration('dataImporterDataObject', 'on', ['executionConfig' => 'not-an-array']),
            $this->configuration('dataImporterDataObject', '1', []),
        ];
        $reader = new DataHubImportConfigurations(new DataHubConfigurationUsage(), $listing);

        $this->assertSame(
            [['cronDefinition' => '0 * * * *'], [], []],
            $reader->activeExecutionConfigs(),
            'inactive and foreign adapters are skipped; a missing or malformed execution config is an empty one',
        );
    }

    /**
     * The active flag is read with the same truthiness the product applies: Data Hub's usage reader casts
     * `isActive()` to bool and the import execution checks plain truthiness, so the string `false` counts
     * as active here exactly as it does for an import run.
     */
    public function testReadsTheActiveFlagWithTheProductsTruthiness(): void
    {
        $listing = fn (): array => [
            $this->configuration('dataImporterDataObject', 'on', ['executionConfig' => ['id' => 1]]),
            $this->configuration('dataImporterDataObject', '1', ['executionConfig' => ['id' => 2]]),
            $this->configuration('dataImporterDataObject', 'false', ['executionConfig' => ['id' => 3]]),
            $this->configuration('dataImporterDataObject', '0', ['executionConfig' => ['id' => 4]]),
            $this->configuration('dataImporterDataObject', '', ['executionConfig' => ['id' => 5]]),
            $this->configuration('dataImporterDataObject', false, ['executionConfig' => ['id' => 6]]),
        ];
        $reader = new DataHubImportConfigurations(new DataHubConfigurationUsage(), $listing);

        $this->assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $reader->activeExecutionConfigs());
    }

    /**
     * The listing is any iterable, so a lazy one may fail only while it is walked; that failure is the
     * same "unreadable" as one at creation time.
     */
    public function testAListingThatFailsWhileIteratingIsUnknownToo(): void
    {
        $listing = function (): iterable {
            yield $this->configuration('dataImporterDataObject', true, ['executionConfig' => []]);

            throw new ConnectionException('store went away mid-listing');
        };
        $reader = new DataHubImportConfigurations(new DataHubConfigurationUsage(), $listing);

        $this->assertNull($reader->activeExecutionConfigs());
    }

    public function testNoConfigurationsIsAnHonestEmptyList(): void
    {
        $reader = new DataHubImportConfigurations(new DataHubConfigurationUsage(), static fn (): array => []);

        $this->assertSame([], $reader->activeExecutionConfigs());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function configuration(string $type, bool|string $active, array $data): Configuration
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getType')->willReturn($type);
        $configuration->method('isActive')->willReturn($active);
        $configuration->method('getConfiguration')->willReturn($data);

        return $configuration;
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
