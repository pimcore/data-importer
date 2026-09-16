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
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataImporterSnapshotCollector;
use Pimcore\Bundle\DataImporterBundle\Telemetry\ImportConfigurationsInterface;

/**
 * The `data_importer.*` namespace over a stubbed configuration reader. Nothing here touches the store.
 */
class DataImporterSnapshotCollectorTest extends Unit
{
    public function testNamespaceIsDataImporter(): void
    {
        $this->assertSame('data_importer', $this->collector([])->getNamespace());
    }

    /**
     * An import is scheduled when its execution configuration can actually fire: a cron schedule with a
     * cron definition (the default schedule type when none is set), or a one-off job with its date.
     * Everything else only runs when somebody starts it.
     */
    public function testCountsScheduledAndManualImports(): void
    {
        $metrics = $this->collector([
            ['scheduleType' => 'cron', 'cronDefinition' => '0 2 * * *'],
            ['cronDefinition' => '*/30 * * * *'],
            ['scheduleType' => 'job', 'scheduledAt' => '01-10-2026 04:00'],
            ['scheduleType' => 'cron', 'cronDefinition' => ''],
            ['scheduleType' => 'job', 'scheduledAt' => ''],
            [],
        ])->collect();

        $this->assertSame(1, $metrics['schema_version'] ?? null);
        $this->assertSame(3, $metrics['config_count_scheduled'] ?? null);
        $this->assertSame(3, $metrics['config_count_manual'] ?? null);
    }

    public function testNoActiveConfigurationIsAnHonestZero(): void
    {
        $metrics = $this->collector([])->collect();

        $this->assertSame(0, $metrics['config_count_scheduled'] ?? null);
        $this->assertSame(0, $metrics['config_count_manual'] ?? null);
    }

    /**
     * A store that cannot be read is unknown: the counts are absent, never zero.
     */
    public function testAnUnreadableStoreLeavesTheCountsAbsent(): void
    {
        $this->assertSame(['schema_version' => 1], $this->collector(null)->collect());
    }

    public function testNothingOfTheConfigurationsLeaves(): void
    {
        $metrics = $this->collector([['scheduleType' => 'cron', 'cronDefinition' => '7 7 * * *']])->collect();

        $this->assertStringNotContainsString('7 7', (string) json_encode($metrics));
    }

    /**
     * @param list<array<string, mixed>>|null $executionConfigs
     */
    private function collector(?array $executionConfigs): DataImporterSnapshotCollector
    {
        $configurations = $this->createStub(ImportConfigurationsInterface::class);
        $configurations->method('activeExecutionConfigs')->willReturn($executionConfigs);

        return new DataImporterSnapshotCollector($configurations);
    }
}
