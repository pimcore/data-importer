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
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\LoaderSettingsCheck;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\AssetLoader;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\HttpLoader;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\SqlLoader;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\UploadLoader;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposedImportConfiguration;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;

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
        $policy = new ImportConfigPolicy(
            $configurations,
            new LoaderSettingsCheck(new ServiceLocator([])),
            $this->createStub(StoredConfigurationsInterface::class)
        );
        $policy->save('car-import', $configuration);

        static::assertSame('car-import', $saved[0]);
        static::assertSame($configuration, $saved[1]);
        // the editor's stale-write guard compares against now, which a reviewed merge passes
        static::assertGreaterThanOrEqual($before, $saved[2]);
        static::assertLessThanOrEqual(time(), $saved[2]);
    }

    public function testItNamesTheImportConfigurationKind(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            new LoaderSettingsCheck(new ServiceLocator([])),
            $this->createStub(StoredConfigurationsInterface::class),
        );

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

    public function testALoaderThatCannotRunIsAProblem(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            new LoaderSettingsCheck(new ServiceLocator([
                'http' => static fn (): HttpLoader => new HttpLoader(new Filesystem()),
            ])),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        static::assertSame(
            ['loaderConfig: Empty Schema.'],
            $policy->problems(
                ['loaderConfig' => ['type' => 'http', 'settings' => ['url' => 'example.com/cars.json']]],
                false
            )
        );
        static::assertSame(
            [],
            $policy->problems(
                ['loaderConfig' => [
                    'type' => 'http',
                    'settings' => ['schema' => 'https://', 'url' => 'example.com/cars.json'],
                ]],
                false
            )
        );
    }

    public function testAStoredLoaderTheMergedStateLeavesAloneIsNotJudged(): void
    {
        $loaderConfig = ['type' => 'asset', 'settings' => ['assetPath' => '']];
        $policy = $this->policyStoring(['general' => ['name' => 'dealer-feed'], 'loaderConfig' => $loaderConfig]);

        // approve judges the merged state: a description change must not fail on the untouched loader
        $problems = $policy->problems([
            'general' => ['name' => 'dealer-feed', 'description' => 'Cars from the dealer feed'],
            'loaderConfig' => ['settings' => ['assetPath' => ''], 'type' => 'asset'],
        ], false);
        static::assertSame([], $problems);
    }

    public function testAChangedStoredLoaderIsJudged(): void
    {
        $policy = $this->policyStoring([
            'general' => ['name' => 'dealer-feed'],
            'loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '/feeds/cars.csv']],
        ]);

        $emptied = [
            'general' => ['name' => 'dealer-feed'],
            'loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '']],
        ];
        static::assertSame(['loaderConfig: Empty asset path.'], $policy->problems($emptied, false));
        // a create has nothing stored to compare with
        static::assertSame(['loaderConfig: Empty asset path.'], $policy->problems($emptied, true));
    }

    public function testSettingsALoaderReadsButTheStateLacksAreAProblem(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            new LoaderSettingsCheck(new ServiceLocator([
                'sql' => static fn (): SqlLoader => new SqlLoader(new Filesystem()),
            ])),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        $problems = $policy->problems(['loaderConfig' => ['type' => 'sql', 'settings' => [
            'connection' => 'default', 'select' => '*', 'from' => 'cars',
        ]]], true);

        static::assertCount(1, $problems);
        static::assertStringStartsWith('loaderConfig: the "sql" loader cannot read its settings', $problems[0]);
        static::assertStringContainsString('where', $problems[0]);
    }

    public function testASettingOfTheWrongTypeIsAProblem(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            new LoaderSettingsCheck(new ServiceLocator([
                'http' => static fn (): HttpLoader => new HttpLoader(new Filesystem()),
            ])),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        static::assertSame(
            ['loaderConfig: a setting of the "http" loader is missing or has the wrong type.'],
            $policy->problems(
                ['loaderConfig' => [
                    'type' => 'http',
                    'settings' => ['url' => ['example.com'], 'schema' => 'https://'],
                ]],
                true
            )
        );
    }

    public function testAnUploadLoaderWithoutItsFileIsNoProblem(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            new LoaderSettingsCheck(new ServiceLocator([
                'upload' => fn (): UploadLoader => new UploadLoader($this->createStub(FilesystemOperator::class)),
            ])),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        // the file can only be uploaded once the configuration exists
        static::assertSame([], $policy->problems(['loaderConfig' => ['type' => 'upload', 'settings' => []]], true));
    }

    /**
     * @param array<string, mixed> $stored
     */
    private function policyStoring(array $stored): ImportConfigPolicy
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getConfiguration')->willReturn($stored);
        $configurations = $this->createStub(StoredConfigurationsInterface::class);
        $configurations->method('find')->willReturnCallback(
            static fn (string $name): ?Configuration => $name === $stored['general']['name'] ? $configuration : null
        );

        return new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            new LoaderSettingsCheck(new ServiceLocator([
                'asset' => static fn (): AssetLoader => new AssetLoader(),
            ])),
            $configurations,
        );
    }
}
