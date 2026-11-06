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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ChangeControl;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposedImportConfiguration;

class ImportConfigPolicyTest extends Unit
{
    public function testAnApprovedConfigurationIsSavedAsProposed(): void
    {
        $configuration = ['general' => ['name' => 'car-import'], 'interpreterConfig' => ['type' => 'csv']];
        $saved = [];
        $configurations = $this->createMock(ConfigurationServiceInterface::class);
        $configurations->expects($this->once())
            ->method('updateConfiguration')
            ->willReturnCallback(static function (string $name, array $config, int $modified) use (&$saved): int {
                $saved = [$name, $config, $modified];

                return $modified;
            });

        $before = time();
        (new ImportConfigPolicy($configurations))->save('car-import', $configuration);

        static::assertSame('car-import', $saved[0]);
        static::assertSame($configuration, $saved[1]);
        // the editor's stale-write guard compares against now, which a reviewed merge passes
        static::assertGreaterThanOrEqual($before, $saved[2]);
        static::assertLessThanOrEqual(time(), $saved[2]);
    }

    public function testItNamesTheImportConfigurationKind(): void
    {
        $policy = new ImportConfigPolicy($this->createStub(ConfigurationServiceInterface::class));

        static::assertSame('dataImporterDataObject', $policy->configType());
        static::assertSame('data-importer-config', $policy->subjectType());
        static::assertSame('import configuration', $policy->noun());
        static::assertSame('get_import_config', $policy->readToolName());
        static::assertSame(ProposedImportConfiguration::SECTIONS, $policy->proposable());
        static::assertSame(ImportConfigPolicy::SLOTS, $policy->slots());
        static::assertSame([], $policy->withheld());
        static::assertSame('', $policy->withheldReason());
        static::assertSame([], $policy->problems(['general' => []], true));
        static::assertSame([], $policy->summarize(['general' => []]));
    }
}
