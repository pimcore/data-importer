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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Mcp;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\LoaderSettingsCheck;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\AssetLoader;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\HttpLoader;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigProposalBuilder;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigVocabulary;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposalRefusedException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;

class ImportConfigProposalBuilderTest extends Unit
{
    private ImportConfigProposalBuilder $builder;

    protected function _before(): void
    {
        $this->builder = new ImportConfigProposalBuilder(
            new ImportConfigVocabulary([]),
            new LoaderSettingsCheck(new ServiceLocator([])),
        );
    }

    /** a reviewed pipeline must not start running because nobody said otherwise */
    public function testANewConfigurationIsProposedInactive(): void
    {
        $state = $this->builder->build('dealer-feed', $this->complete(), null);

        static::assertFalse($state['general']['active']);
        static::assertSame('', $state['general']['path']);
        static::assertSame('dealer-feed', $state['general']['name']);
        static::assertSame(ImportConfigPolicy::CONFIG_TYPE, $state['general']['type']);
    }

    public function testANewConfigurationSwitchedOnStaysOn(): void
    {
        $document = $this->complete();
        $document['general'] = ['active' => true];

        static::assertTrue($this->builder->build('dealer-feed', $document, null)['general']['active']);
    }

    public function testTheProposalNeverNamesAnotherConfigurationOrAdapter(): void
    {
        $document = $this->complete();
        $document['general'] = ['name' => 'other', 'type' => 'graphql', 'modificationDate' => 1];

        $state = $this->builder->build('dealer-feed', json_encode($document, JSON_THROW_ON_ERROR), null);

        static::assertSame('dealer-feed', $state['general']['name']);
        static::assertSame(ImportConfigPolicy::CONFIG_TYPE, $state['general']['type']);
        static::assertArrayNotHasKey('modificationDate', $state['general']);
    }

    public function testANewConfigurationLackingItsCoreIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('A new configuration needs interpreterConfig.type');
        $this->builder->build('dealer-feed', ['loaderConfig' => ['type' => 'asset']], null);
    }

    public function testANameThatCannotBeAFileIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('"../cars" cannot name a configuration');
        $this->builder->build('../cars', $this->complete(), null);
    }

    public function testAChangeKeepsWhatItLeavesOut(): void
    {
        $stored = $this->complete();
        $stored['general'] = ['name' => 'dealer-feed', 'active' => true, 'modificationDate' => 1700000000];

        $state = $this->builder->build(
            'dealer-feed',
            ['interpreterConfig' => ['type' => 'xml']],
            $this->stored($stored),
        );

        static::assertSame('xml', $state['interpreterConfig']['type']);
        static::assertSame($stored['loaderConfig'], $state['loaderConfig']);
        static::assertTrue($state['general']['active']);
        static::assertArrayNotHasKey('modificationDate', $state['general']);
    }

    public function testAnInventedSectionIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('schedule is not part of an import configuration');
        $this->builder->build('dealer-feed', ['schedule' => ['cron' => '* * * * *']], $this->stored($this->complete()));
    }

    public function testADocumentThatIsNoObjectIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('The configuration must be an object');
        $this->builder->build('dealer-feed', 'just words', $this->stored($this->complete()));
    }

    public function testAMappingThatIsNoListIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('mappingConfig must be a list');
        $this->builder->build('dealer-feed', ['mappingConfig' => 'vin'], $this->stored($this->complete()));
    }

    public function testATypeTheInstallationLacksIsRefused(): void
    {
        $builder = new ImportConfigProposalBuilder(
            new ImportConfigVocabulary(['interpreter' => ['csv' => new \stdClass()]]),
            new LoaderSettingsCheck(new ServiceLocator([])),
        );

        $this->expectException(ProposalRefusedException::class);
        $builder->build('dealer-feed', ['interpreterConfig' => ['type' => 'xml']], $this->stored($this->complete()));
    }

    public function testAnUnreadableYamlDocumentIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('The configuration must be an object');
        $this->builder->build('dealer-feed', 'interpreterConfig: [json', $this->stored($this->complete()));
    }

    public function testAnInventedFieldIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('resolverConfig.flavour is not a field of an import configuration');
        $this->builder->build(
            'dealer-feed',
            ['resolverConfig' => ['flavour' => 'x']],
            $this->stored($this->complete())
        );
    }

    public function testAChangeTheImportWouldIgnoreIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage(
            'processingConfig.doDeltaCheck has no effect while processingConfig.idDataIndex is off'
        );
        $this->builder->build(
            'dealer-feed',
            ['processingConfig' => ['doDeltaCheck' => true]],
            $this->stored($this->complete())
        );
    }

    public function testAnHttpLoaderWithoutItsSchemaIsRefused(): void
    {
        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('loaderConfig: Empty Schema.');
        $this->withLoaders()->build(
            'dealer-feed',
            ['loaderConfig' => ['type' => 'http', 'settings' => ['url' => 'example.com/cars.json']]],
            $this->stored($this->complete())
        );
    }

    public function testAnHttpLoaderWithSchemaAndUrlIsProposed(): void
    {
        $state = $this->withLoaders()->build(
            'dealer-feed',
            ['loaderConfig' => ['type' => 'http', 'settings' => ['schema' => 'https://', 'url' => 'example.com/cars.json']]],
            $this->stored($this->complete())
        );

        static::assertSame('http', $state['loaderConfig']['type']);
        static::assertSame('https://', $state['loaderConfig']['settings']['schema']);
    }

    /** the asset loader's path means nothing to the HTTP loader, so it must not ride along */
    public function testASwitchFromAssetToHttpCarriesNoAssetPath(): void
    {
        $state = $this->withLoaders()->build(
            'dealer-feed',
            ['loaderConfig' => ['type' => 'http', 'settings' => ['schema' => 'https://', 'url' => 'example.com/cars.json']]],
            $this->stored($this->complete())
        );

        static::assertSame(
            ['schema' => 'https://', 'url' => 'example.com/cars.json'],
            $state['loaderConfig']['settings']
        );
    }

    /** a loader stored without its settings is not the proposal's to refuse when it leaves it alone */
    public function testAStoredLoaderTheProposalLeavesAloneIsNotJudged(): void
    {
        $stored = $this->complete();
        $stored['loaderConfig']['settings']['assetPath'] = '';

        $state = $this->withLoaders()->build(
            'dealer-feed',
            ['general' => ['description' => 'Cars from the dealer feed']],
            $this->stored($stored)
        );

        static::assertSame('Cars from the dealer feed', $state['general']['description']);
    }

    public function testANewConfigurationWithALoaderThatCannotRunIsRefused(): void
    {
        $document = $this->complete();
        $document['loaderConfig']['settings'] = [];

        $this->expectException(ProposalRefusedException::class);
        $this->expectExceptionMessage('loaderConfig: Empty asset path.');
        $this->withLoaders()->build('dealer-feed', $document, null);
    }

    private function withLoaders(): ImportConfigProposalBuilder
    {
        return new ImportConfigProposalBuilder(
            new ImportConfigVocabulary([]),
            new LoaderSettingsCheck(new ServiceLocator([
                'asset' => static fn (): AssetLoader => new AssetLoader(),
                'http' => static fn (): HttpLoader => new HttpLoader(new Filesystem()),
            ])),
        );
    }

    /** @return array<string, mixed> */
    private function complete(): array
    {
        return [
            'loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '/feeds/cars.json']],
            'interpreterConfig' => ['type' => 'json'],
            'resolverConfig' => [
                'dataObjectClassId' => 'CAR',
                'loadingStrategy' => ['type' => 'notLoad'],
                'createLocationStrategy' => ['type' => 'staticPath', 'settings' => ['path' => '/import']],
                'locationUpdateStrategy' => ['type' => 'noChange'],
                'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
            ],
        ];
    }

    /** @param array<string, mixed> $configuration */
    private function stored(array $configuration): Configuration
    {
        $stored = $this->createStub(Configuration::class);
        $stored->method('getConfiguration')->willReturn($configuration);
        $stored->method('getType')->willReturn(ImportConfigPolicy::CONFIG_TYPE);

        return $stored;
    }
}
