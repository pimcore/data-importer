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
use Doctrine\DBAL\ConnectionException;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Telemetry\DataHubConfigurationUsage;
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataHubImportConfigurations;

/**
 * The concrete Data Hub reader behind the data_importer.* metrics: which configurations reach telemetry,
 * what part of them, and what an unreadable listing becomes.
 */
class DataHubImportConfigurationsTest extends Unit
{
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
}
