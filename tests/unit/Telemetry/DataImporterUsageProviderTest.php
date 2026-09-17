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
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataImporterUsageProvider;
use Pimcore\Bundle\DataImporterBundle\Telemetry\ImportConfigurationsInterface;

class DataImporterUsageProviderTest extends Unit
{
    public function testReportsUnderTheExpectedKey(): void
    {
        $this->assertSame('data_importer', $this->provider(true)->getBundleKey());
    }

    public function testAnActiveImportConfigurationIsUsed(): void
    {
        $this->assertTrue($this->provider(true)->isUsed());
    }

    /**
     * No active import is a real "installed and not set up" and must not be blurred into unknown.
     */
    public function testNoActiveImportIsNotUsedRatherThanUnknown(): void
    {
        $used = $this->provider(false)->isUsed();

        $this->assertFalse($used);
        $this->assertNotNull($used);
    }

    /**
     * Data Hub configuration can live in the settings store or in Symfony config files; an unreadable
     * store must stay unknown, because `false` there would invent an adoption gap for every customer on
     * the target the read could not reach.
     */
    public function testAnUnreadableStoreIsPassedThroughAsUnknown(): void
    {
        $this->assertNull($this->provider(null)->isUsed());
    }

    private function provider(?bool $hasActive): DataImporterUsageProvider
    {
        $configurations = $this->createMock(ImportConfigurationsInterface::class);
        $configurations->method('hasActive')->willReturn($hasActive);

        return new DataImporterUsageProvider($configurations);
    }
}
