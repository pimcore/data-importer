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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Settings;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPathMapper;

class ConfigurationPathMapperTest extends Unit
{
    private const string ID_PRICE = '4ac0f1d2-0000-4000-8000-00000000002f';

    private const string ID_VIN = '1a44b7e6-0000-4000-8000-000000000008';

    private ConfigurationPathMapper $mapper;

    protected function _before(): void
    {
        $this->mapper = new ConfigurationPathMapper();
    }

    public function testGeneralKeysAreLiftedToTheFormRoot(): void
    {
        foreach (['active', 'description', 'group', 'name'] as $key) {
            static::assertSame($key, $this->mapper->toFormPath('general.' . $key, $this->configuration()));
            static::assertSame('general.' . $key, $this->mapper->toStoredPath($key, $this->configuration()));
        }
    }

    public function testGeneralKeyTheFormDoesNotBindHasNoFormPath(): void
    {
        // written by the API, never rendered - a review must not offer it as a field
        static::assertNull($this->mapper->toFormPath('general.modificationDate', $this->configuration()));
        static::assertNull($this->mapper->toFormPath('general.type', $this->configuration()));
    }

    public function testSectionsWithTheSameShapeMapToThemselves(): void
    {
        $paths = [
            'loaderConfig.settings.path',
            'interpreterConfig.settings.delimiter',
            'resolverConfig.loadingStrategy.settings.attributeName',
            'processingConfig.cleanupStrategy',
            'executionConfig.cronDefinition',
        ];

        foreach ($paths as $path) {
            static::assertSame($path, $this->mapper->toFormPath($path, $this->configuration()));
            static::assertSame($path, $this->mapper->toStoredPath($path, $this->configuration()));
        }
    }

    public function testMappingItemsAreAddressedByIdWhenStoredAndByIndexInTheForm(): void
    {
        static::assertSame(
            'mappingConfig.1.dataTarget.settings.fieldName',
            $this->mapper->toFormPath(
                'mappingConfig.' . self::ID_PRICE . '.dataTarget.settings.fieldName',
                $this->configuration()
            )
        );

        static::assertSame(
            'mappingConfig.' . self::ID_PRICE . '.dataTarget.settings.fieldName',
            $this->mapper->toStoredPath('mappingConfig.1.dataTarget.settings.fieldName', $this->configuration())
        );
    }

    public function testWholeMappingItemRoundTrips(): void
    {
        static::assertSame('mappingConfig.0', $this->mapper->toFormPath('mappingConfig.' . self::ID_VIN, $this->configuration()));
        static::assertSame('mappingConfig.' . self::ID_VIN, $this->mapper->toStoredPath('mappingConfig.0', $this->configuration()));
    }

    /**
     * The asymmetry a review has to handle: a proposal that ADDS a mapping names an id the
     * current configuration has no row for, so there is no index to annotate. The surface has
     * to insert the row before it can point at it.
     */
    public function testAddedMappingItemHasNoFormPathYet(): void
    {
        static::assertNull(
            $this->mapper->toFormPath('mappingConfig.b1180000-0000-4000-8000-00000000009a.dataTarget', $this->configuration())
        );
    }

    public function testIndexBeyondTheStoredListHasNoStoredPath(): void
    {
        static::assertNull($this->mapper->toStoredPath('mappingConfig.9.dataTarget', $this->configuration()));
        static::assertNull($this->mapper->toStoredPath('mappingConfig.notAnIndex.dataTarget', $this->configuration()));
    }

    /**
     * `mappingId` is minted in the Studio form, so a configuration written before it - or by
     * the console - carries none. Those rows are not addressable by id at all, which is why
     * they are skipped rather than given a positional key.
     */
    public function testItemsWithoutAMappingIdAreNotAddressable(): void
    {
        $legacy = ['mappingConfig' => [['dataSourceIndex' => 'vin'], ['dataSourceIndex' => 'price']]];

        static::assertSame([], $this->mapper->mappingIndex($legacy));
        static::assertNull($this->mapper->toStoredPath('mappingConfig.0.dataTarget', $legacy));
    }

    public function testMappingIndexSkipsOnlyTheItemsWithoutAnId(): void
    {
        $mixed = ['mappingConfig' => [
            ['dataSourceIndex' => 'legacy'],
            ['mappingId' => self::ID_VIN, 'dataSourceIndex' => 'vin'],
        ]];

        static::assertSame([self::ID_VIN => 1], $this->mapper->mappingIndex($mixed));
    }

    public function testEmptyPathHasNoCounterpart(): void
    {
        static::assertNull($this->mapper->toFormPath('', $this->configuration()));
        static::assertNull($this->mapper->toStoredPath('', $this->configuration()));
    }

    public function testMappingConfigItselfIsNotAnItemAddress(): void
    {
        static::assertSame('mappingConfig', $this->mapper->toFormPath('mappingConfig', $this->configuration()));
        static::assertSame('mappingConfig', $this->mapper->toStoredPath('mappingConfig', $this->configuration()));
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        return [
            'general' => [
                'name' => 'csv-car-import',
                'active' => true,
                'description' => 'Nightly vehicle master import',
                'group' => 'Vehicle data',
                'type' => 'dataImporterDataObject',
                'modificationDate' => 1788000000,
            ],
            'mappingConfig' => [
                ['mappingId' => self::ID_VIN, 'dataSourceIndex' => 'vin'],
                ['mappingId' => self::ID_PRICE, 'dataSourceIndex' => 'list_price'],
            ],
        ];
    }
}
