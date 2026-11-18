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
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\HttpLoader;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\SqlLoader;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\UploadLoader;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposedImportConfiguration;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ImportConfigCheckFixture;
use Symfony\Component\Filesystem\Filesystem;

class ImportConfigPolicyTest extends Unit
{
    private const string HTTP_SHAPE = 'The "http" loader needs settings.schema ("https://" or "http://") and '
        . 'settings.url (the address without its scheme, e.g. "example.com/feed.json").';

    private const string MISSING_FIELD = 'doesNotExist';

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
            ImportConfigCheckFixture::check(),
            $this->createStub(StoredConfigurationsInterface::class)
        );
        $policy->save('car-import', $configuration);

        static::assertSame('car-import', $saved[0]);
        static::assertSame($configuration, $saved[1]);
        // the editor's stale-write guard compares against now, which a reviewed merge passes
        static::assertGreaterThanOrEqual($before, $saved[2]);
        static::assertLessThanOrEqual(time(), $saved[2]);
    }

    /** a review compares mapping rows without the ids the editor mints; the approval puts them back */
    public function testAReviewComparesMappingRowsWithoutTheirIdsAndAnApprovalRestoresThem(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            ImportConfigCheckFixture::check(),
            $this->createStub(StoredConfigurationsInterface::class),
        );
        $row = ['label' => 'VIN', 'dataSourceIndex' => ['vin']];
        $stored = ['general' => ['name' => 'car-import'], 'mappingConfig' => [$row + ['mappingId' => 'm1']]];

        static::assertSame(
            ['general' => ['name' => 'car-import'], 'mappingConfig' => [$row]],
            $policy->comparable($stored),
        );
        static::assertSame($stored, $policy->restored($policy->comparable($stored), $stored));
    }

    public function testItNamesTheImportConfigurationKind(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            ImportConfigCheckFixture::check(),
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
            ImportConfigCheckFixture::check([
                'http' => static fn (): HttpLoader => new HttpLoader(new Filesystem()),
            ]),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        static::assertSame(
            ['loaderConfig: Empty Schema. ' . self::HTTP_SHAPE],
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
            ImportConfigCheckFixture::check([
                'sql' => static fn (): SqlLoader => new SqlLoader(new Filesystem()),
            ]),
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
            ImportConfigCheckFixture::check([
                'http' => static fn (): HttpLoader => new HttpLoader(new Filesystem()),
            ]),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        static::assertSame(
            ['loaderConfig: a setting of the "http" loader is missing or has the wrong type. ' . self::HTTP_SHAPE],
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
            ImportConfigCheckFixture::check([
                'upload' => fn (): UploadLoader => new UploadLoader($this->createStub(FilesystemOperator::class)),
            ]),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        // the file can only be uploaded once the configuration exists
        static::assertSame([], $policy->problems(['loaderConfig' => ['type' => 'upload', 'settings' => []]], true));
    }

    public function testSettingsThatAreNoMapAreJudgedAsEmpty(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            ImportConfigCheckFixture::check([
                'http' => static fn (): HttpLoader => new HttpLoader(new Filesystem()),
            ]),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        static::assertSame(
            ['loaderConfig: Empty URL. ' . self::HTTP_SHAPE],
            $policy->problems(['loaderConfig' => ['type' => 'http', 'settings' => 'https://example.com']], true)
        );
    }

    /** a review amendment never met the vocabulary the propose tool refuses an unknown type with */
    public function testATypeThatNamesNoLoaderIsAProblem(): void
    {
        $policy = new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            ImportConfigCheckFixture::check([
                'other' => static fn (): Filesystem => new Filesystem(),
            ]),
            $this->createStub(StoredConfigurationsInterface::class),
        );

        static::assertSame(
            ['loaderConfig.type: "unknown" is not a loader here.'],
            $policy->problems(['loaderConfig' => ['type' => 'unknown', 'settings' => []]], true)
        );
        static::assertSame([], $policy->problems(['loaderConfig' => ['type' => 'other', 'settings' => []]], true));
        static::assertSame(['loaderConfig must be an object.'], $policy->problems(['loaderConfig' => 'http'], true));
    }

    /** an amendment made in the review never met the propose tool's checks */
    public function testABrokenReviewAmendmentIsAProblemAtApproval(): void
    {
        $stored = $this->dealerFeed();
        $policy = $this->policyStoring($stored);

        $amended = $stored;
        $amended['mappingConfig'][0]['dataTarget']['settings']['fieldName'] = self::MISSING_FIELD;
        $amended['interpreterConfig'] = ['type' => 'xml', 'settings' => []];

        static::assertSame(
            [
                'interpreterConfig: Empty XPath.',
                'mappingConfig[0].dataTarget.settings.fieldName "doesNotExist" is not a field of class '
                    . ImportConfigCheckFixture::CLASS_NAME . '.',
            ],
            $policy->problems($amended, false)
        );
    }

    /** an approval judges the merged state: a stored section nobody touched never blocks it */
    public function testABrokenStoredSectionTheApprovalLeavesAloneIsNoProblem(): void
    {
        $stored = $this->dealerFeed();
        $stored['mappingConfig'][0]['dataTarget']['settings']['fieldName'] = self::MISSING_FIELD;
        $stored['executionConfig'] = ['scheduleType' => 'cron', 'cronDefinition' => 'every night'];
        $policy = $this->policyStoring($stored);

        $approved = $stored;
        $approved['general']['active'] = true;
        $approved['mappingConfig'][] = ['label' => '', 'dataSourceIndex' => ['model'], 'dataTarget' => [
            'type' => 'direct',
            'settings' => ['fieldName' => 'name'],
        ]];

        static::assertSame([], $policy->problems($approved, false));
    }

    public function testAStoredLoaderSettingThatChangesOnlyItsTypeIsJudged(): void
    {
        $settings = ['connection' => 'default', 'select' => '*', 'from' => 'cars', 'where' => ''];
        $policy = $this->policyStoring(
            ['general' => ['name' => 'dealer-feed'], 'loaderConfig' => ['type' => 'sql', 'settings' => $settings]],
            ['sql' => static fn (): SqlLoader => new SqlLoader(new Filesystem())],
        );

        // a review amendment "" -> null never met the propose tool's checks, and the loader cannot read it
        static::assertSame(
            ['loaderConfig: a setting of the "sql" loader is missing or has the wrong type.'],
            $policy->problems([
                'general' => ['name' => 'dealer-feed'],
                'loaderConfig' => ['type' => 'sql', 'settings' => ['where' => null] + $settings],
            ], false)
        );

        // the same settings in another key order are still untouched
        static::assertSame(
            [],
            $policy->problems([
                'general' => ['name' => 'dealer-feed'],
                'loaderConfig' => ['settings' => array_reverse($settings), 'type' => 'sql'],
            ], false)
        );
    }

    /** @return array<string, mixed> */
    private function dealerFeed(): array
    {
        return [
            'general' => ['name' => 'dealer-feed', 'active' => false],
            'loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '/feeds/cars.json']],
            'interpreterConfig' => ['type' => 'json'],
            'resolverConfig' => [
                'dataObjectClassId' => ImportConfigCheckFixture::CLASS_ID,
                'loadingStrategy' => ['type' => 'notLoad'],
                'createLocationStrategy' => ['type' => 'staticPath', 'settings' => ['path' => '/import']],
                'locationUpdateStrategy' => ['type' => 'noChange'],
                'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
            ],
            'mappingConfig' => [[
                'label' => 'VIN',
                'dataSourceIndex' => ['vin'],
                'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'vin']],
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, callable> $loaders
     */
    private function policyStoring(array $stored, array $loaders = []): ImportConfigPolicy
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getConfiguration')->willReturn($stored);
        $configurations = $this->createStub(StoredConfigurationsInterface::class);
        $configurations->method('find')->willReturnCallback(
            static fn (string $name): ?Configuration => $name === $stored['general']['name'] ? $configuration : null
        );

        return new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            ImportConfigCheckFixture::check($loaders),
            $configurations,
        );
    }
}
