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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposedImportConfiguration;

class ProposedImportConfigurationTest extends Unit
{
    public function testASectionLeftOutKeepsItsStoredValue(): void
    {
        $folded = ProposedImportConfiguration::fold($this->stored(), [
            'general' => ['description' => 'Nightly import'],
        ]);

        static::assertSame('Nightly import', $folded['general']['description']);
        static::assertTrue($folded['general']['active']);
        static::assertSame($this->stored()['processingConfig'], $folded['processingConfig']);
    }

    public function testALeafInsideASectionFoldsWithoutDroppingItsSiblings(): void
    {
        $folded = ProposedImportConfiguration::fold($this->stored(), [
            'processingConfig' => ['cleanup' => ['strategy' => 'delete']],
        ]);

        static::assertSame('delete', $folded['processingConfig']['cleanup']['strategy']);
        static::assertSame('parallel', $folded['processingConfig']['executionType']);
    }

    /**
     * Merging lists index by index grafts the stored row's keys onto a row the agent rewrote,
     * and leaves the tail of a longer stored list behind a shorter proposed one.
     */
    public function testAListIsReplacedWholeNotMergedByIndex(): void
    {
        $folded = ProposedImportConfiguration::fold($this->stored(), [
            'mappingConfig' => [
                ['label' => 'series', 'dataSourceIndex' => ['series']],
            ],
        ]);

        static::assertSame([['label' => 'series', 'dataSourceIndex' => ['series']]], $folded['mappingConfig']);
    }

    public function testAPlainListIsAMappingList(): void
    {
        $rows = [['label' => 'a'], ['label' => 'b']];

        static::assertSame($rows, ProposedImportConfiguration::mappingList($rows));
    }

    public function testAWrappedListIsUnwrapped(): void
    {
        $rows = [['label' => 'a'], ['label' => 'b']];

        static::assertSame($rows, ProposedImportConfiguration::mappingList(['mappings' => $rows]));
    }

    public function testAListKeyedByIndexIsAList(): void
    {
        static::assertSame(
            [['label' => 'a'], ['label' => 'b']],
            ProposedImportConfiguration::mappingList(['0' => ['label' => 'a'], '1' => ['label' => 'b']])
        );
    }

    /**
     * @dataProvider notAMappingList
     */
    public function testAnythingElseIsNotAMappingList(mixed $value): void
    {
        static::assertNull(ProposedImportConfiguration::mappingList($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notAMappingList(): iterable
    {
        yield 'a string' => ['series'];
        yield 'an object with its own keys' => [['series' => ['label' => 'series']]];
        yield 'a wrapper around something else' => [['mappings' => 'series']];
        yield 'a gap in the index' => [['0' => ['label' => 'a'], '2' => ['label' => 'b']]];
    }

    public function testAnInventedSectionIsUnknown(): void
    {
        static::assertSame(
            ['schedule'],
            ProposedImportConfiguration::unknownSections(['general' => [], 'schedule' => []], $this->stored())
        );
    }

    /** an installation may store more than the editor shows; that is not an invention */
    public function testAKeyTheStoredDocumentCarriesIsKnown(): void
    {
        static::assertSame(
            [],
            ProposedImportConfiguration::unknownSections(['workspaces' => []], $this->stored())
        );
    }

    public function testATypeTheInstallationHasIsAccepted(): void
    {
        $state = $this->stored();
        $state['resolverConfig'] = ['publishingStrategy' => ['type' => 'alwaysPublish']];

        static::assertSame([], ProposedImportConfiguration::unknownValues($state, $this->vocabulary()));
    }

    /** the editor's select cannot hold "publishNew"; neither can a proposal */
    public function testAnInventedStrategyIsNamedWithTheAllowedOnes(): void
    {
        $state = $this->stored();
        $state['resolverConfig'] = ['publishingStrategy' => ['type' => 'publishNew']];

        $problems = ProposedImportConfiguration::unknownValues($state, $this->vocabulary());

        static::assertCount(1, $problems);
        static::assertStringContainsString('resolverConfig.publishingStrategy.type: "publishNew"', $problems[0]);
        static::assertStringContainsString('alwaysPublish, noChangeUnpublishNew', $problems[0]);
    }

    public function testMappingTargetsAndOperatorsAreCheckedRowByRow(): void
    {
        $state = $this->stored();
        $state['mappingConfig'][1]['dataTarget'] = ['type' => 'magic'];
        $state['mappingConfig'][1]['transformationPipeline'] = [['type' => 'trim'], ['type' => 'shout']];

        $problems = ProposedImportConfiguration::unknownValues($state, $this->vocabulary());

        static::assertCount(2, $problems);
        static::assertStringContainsString('mappingConfig[1].dataTarget.type: "magic"', $problems[0]);
        static::assertStringContainsString('mappingConfig[1].transformationPipeline[1].type: "shout"', $problems[1]);
    }

    /** a family the installation does not register is not checked: it is not this tool's call */
    public function testAnUnknownFamilyIsLeftAlone(): void
    {
        $state = $this->stored();
        $state['loaderConfig'] = ['type' => 'carrier-pigeon'];

        static::assertSame([], ProposedImportConfiguration::unknownValues($state, ['interpreter' => ['csv']]));
    }

    /**
     * @dataProvider names
     */
    public function testANameIsWhatAFileAndAYamlKeyAccept(string $name, bool $valid): void
    {
        static::assertSame($valid, ProposedImportConfiguration::isValidName($name));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        yield 'plain' => ['car-import', true];
        yield 'underscore and digits' => ['dealer_feed_2', true];
        yield 'spaces' => ['car import', false];
        yield 'leading dash' => ['-cars', false];
        yield 'path' => ['../cars', false];
        yield 'empty' => ['', false];
    }

    public function testACompleteDocumentLacksNothingToCreate(): void
    {
        $state = $this->stored();
        $state['interpreterConfig'] = ['type' => 'json'];
        $state['resolverConfig'] = [
            'dataObjectClassId' => 'CAR',
            'loadingStrategy' => ['type' => 'notLoad'],
            'createLocationStrategy' => ['type' => 'staticPath', 'settings' => ['path' => '/import']],
            'locationUpdateStrategy' => ['type' => 'noChange'],
            'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
        ];

        static::assertSame([], ProposedImportConfiguration::missingForCreate($state));
    }

    /** an update inherits these from the stored document; a create has nowhere to inherit from */
    public function testACreateNamesWhatItStillLacks(): void
    {
        $state = ['general' => ['name' => 'dealer-feed'], 'loaderConfig' => ['type' => 'asset'], 'resolverConfig' => ['dataObjectClassId' => '']];

        static::assertSame([
            'interpreterConfig.type',
            'resolverConfig.dataObjectClassId',
            'resolverConfig.loadingStrategy.type',
            'resolverConfig.createLocationStrategy.type',
            'resolverConfig.locationUpdateStrategy.type',
            'resolverConfig.publishingStrategy.type',
        ], ProposedImportConfiguration::missingForCreate($state));
    }

    /**
     * @return array<string, list<string>>
     */
    private function vocabulary(): array
    {
        return [
            ProposedImportConfiguration::FAMILY_LOADER => ['asset', 'sftp'],
            ProposedImportConfiguration::FAMILY_INTERPRETER => ['csv', 'json'],
            ProposedImportConfiguration::FAMILY_PUBLISHING => ['alwaysPublish', 'noChangeUnpublishNew'],
            ProposedImportConfiguration::FAMILY_DATA_TARGET => ['direct'],
            ProposedImportConfiguration::FAMILY_OPERATOR => ['trim'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        return [
            'general' => ['active' => true, 'type' => 'dataImporterDataObject', 'name' => 'car-import', 'description' => ''],
            'loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '/import/cars.json']],
            'processingConfig' => ['executionType' => 'parallel', 'cleanup' => ['strategy' => 'unpublish']],
            'mappingConfig' => [
                ['label' => 'manufacturer', 'dataSourceIndex' => ['manufacturer'], 'transformationPipeline' => []],
                ['label' => 'model', 'dataSourceIndex' => ['model'], 'transformationPipeline' => []],
            ],
            'workspaces' => [],
        ];
    }
}
