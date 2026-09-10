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
