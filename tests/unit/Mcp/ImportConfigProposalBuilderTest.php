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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigProposalBuilder;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigVocabulary;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposalRefusedException;

class ImportConfigProposalBuilderTest extends Unit
{
    private ImportConfigProposalBuilder $builder;

    protected function _before(): void
    {
        $this->builder = new ImportConfigProposalBuilder(new ImportConfigVocabulary([]));
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
            new ImportConfigVocabulary(['interpreter' => ['csv' => new \stdClass()]])
        );

        $this->expectException(ProposalRefusedException::class);
        $builder->build('dealer-feed', ['interpreterConfig' => ['type' => 'xml']], $this->stored($this->complete()));
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
