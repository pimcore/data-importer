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
use Pimcore\Bundle\ChangeControlBundle\Hydrator\SlotDetail;
use Pimcore\Bundle\ChangeControlBundle\Subject\SubjectRef;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigDetailHydrator;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigSubjectHandler;
use Symfony\Component\Uid\Uuid;

class ImportConfigDetailHydratorTest extends Unit
{
    private ImportConfigDetailHydrator $hydrator;

    private SubjectRef $subject;

    protected function _before(): void
    {
        // the Change Control bundle is an optional integration this repository cannot install
        if (!class_exists(SlotDetail::class)) {
            static::markTestSkipped('Change Control bundle not installed');
        }

        $this->hydrator = new ImportConfigDetailHydrator();
        $this->subject = new SubjectRef(ImportConfigSubjectHandler::TYPE, Uuid::v4(), 'car-import');
    }

    public function testSectionsBecomeTheEditorsOwnSlots(): void
    {
        $slots = $this->hydrator->hydrate($this->subject, $this->configuration());

        static::assertSame(
            ['general', 'dataSource', 'processing', 'execution', 'mapping'],
            array_keys($slots)
        );
    }

    public function testLoaderAndInterpreterShareTheDataSourceSlot(): void
    {
        $slots = $this->hydrator->hydrate($this->subject, $this->configuration());

        static::assertSame([
            'loaderConfig.type',
            'loaderConfig.settings.path',
            'interpreterConfig.type',
        ], array_keys($slots['dataSource']->values));
    }

    /**
     * Addresses are DOCUMENT paths: the merge takes them back as exclude paths, so they have
     * to name a place in the stored tree, not in the editor's form.
     */
    public function testAddressesAreDocumentPathsNotFormPaths(): void
    {
        $slots = $this->hydrator->hydrate($this->subject, $this->configuration());

        static::assertArrayHasKey('general.description', $slots['general']->values);
        static::assertArrayNotHasKey('description', $slots['general']->values);
    }

    public function testScalarLeavesAreRecordsAndTheMappingListIsNot(): void
    {
        $slots = $this->hydrator->hydrate($this->subject, $this->configuration());

        static::assertSame(SlotDetail::SHAPE_RECORD, $slots['general']->shape);
        static::assertSame('import-mapping-list', $slots['mapping']->shape);
    }

    /**
     * The mapping list rides ONE address, whole: a positional address into a list stops being
     * stable the moment a row is inserted, so "the mappings" is the only honest unit here.
     */
    public function testTheMappingListIsOneAddressCarryingEveryRow(): void
    {
        $slots = $this->hydrator->hydrate($this->subject, $this->configuration());

        static::assertSame(['mappingConfig'], array_keys($slots['mapping']->values));
        static::assertCount(2, $slots['mapping']->values['mappingConfig']);
    }

    /** a list is a value, not a branch: its members have no address of their own */
    public function testListsAreKeptWholeRatherThanFlattenedByPosition(): void
    {
        $slots = $this->hydrator->hydrate($this->subject, ['resolverConfig' => ['allowedTypes' => ['a', 'b']]]);

        static::assertSame(['a', 'b'], $slots['resolver']->values['resolverConfig.allowedTypes']);
    }

    public function testASectionTheHydratorDoesNotNameStillReachesTheReviewer(): void
    {
        $slots = $this->hydrator->hydrate($this->subject, ['workspaces' => ['object' => ['read' => true]]]);

        static::assertArrayHasKey('workspaces', $slots);
        static::assertSame(true, $slots['workspaces']->values['workspaces.object.read']);
    }

    public function testDehydrateKeepsOnlyAddressesTheProposedTreeCanTakeBack(): void
    {
        $kept = $this->hydrator->dehydrate(
            $this->subject,
            ['general.description' => 'new', 'invented.leaf' => 'x'],
            ['general' => ['description' => 'old']]
        );

        static::assertSame(['general.description' => 'new'], $kept);
    }

    /** a create has no tree to filter against: the whole document is the patch */
    public function testDehydrateKeepsEveryLeafWhenNothingIsRecordedYet(): void
    {
        $patch = ['general' => ['name' => 'dealer-feed-import'], 'mappingConfig' => [['label' => 'vin']]];

        static::assertSame($patch, $this->hydrator->dehydrate($this->subject, $patch, []));
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        return [
            'general' => ['name' => 'car-import', 'active' => true, 'description' => 'Nightly import'],
            'loaderConfig' => ['type' => 'asset', 'settings' => ['path' => '/Import Test/car-export.json']],
            'interpreterConfig' => ['type' => 'json'],
            'processingConfig' => ['cleanupStrategy' => 'unpublish'],
            'executionConfig' => ['cronDefinition' => '0 2 * * *'],
            'mappingConfig' => [
                ['label' => 'manufacturer', 'dataTarget' => ['type' => 'direct']],
                ['label' => 'mileage', 'dataTarget' => ['type' => 'direct']],
            ],
        ];
    }
}
