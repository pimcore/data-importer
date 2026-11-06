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
use Pimcore\Bundle\ChangeControlBundle\Merge\EditableDataMerge;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\ChangeControl\ConfigStateShape;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigProposalBuilder;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigVocabulary;

/**
 * Approval merges a proposal over the configuration as stored then, against the state stored
 * when it was proposed: an edit made in between survives or refuses the approval, never lost.
 */
class ImportConfigMergeTest extends Unit
{
    private EditableDataMerge $merge;

    protected function _before(): void
    {
        // Change Control is an optional integration this repository cannot install
        if (!class_exists(EditableDataMerge::class) || !class_exists(ConfigStateShape::class)) {
            static::markTestSkipped('Change Control bundle or Data Hub proposal lane not installed');
        }
        $this->merge = new EditableDataMerge(new ConfigStateShape());
    }

    public function testALaterEditOfAFieldTheProposalChangedRefusesTheApproval(): void
    {
        $base = $this->stored();
        $proposed = $this->propose($base, ['interpreterConfig' => ['type' => 'xml']]);
        $main = $base;
        $main['interpreterConfig']['type'] = 'csv';

        $conflicts = $this->merge->conflictsDeep($base, $main, $this->merge->deltaOf($base, $proposed));

        static::assertSame(['interpreterConfig.type'], array_column($conflicts, 'path'));
    }

    public function testALaterEditOfAnotherFieldSurvivesTheApproval(): void
    {
        $base = $this->stored();
        $proposed = $this->propose($base, ['interpreterConfig' => ['type' => 'xml']]);
        $main = $base;
        $main['loaderConfig']['settings']['assetPath'] = '/feeds/trucks.json';
        $delta = $this->merge->deltaOf($base, $proposed);

        $merged = $this->merge->deepOverlay($main, $delta);

        static::assertSame([], $this->merge->conflictsDeep($base, $main, $delta));
        static::assertSame('/feeds/trucks.json', $merged['loaderConfig']['settings']['assetPath']);
        static::assertSame('xml', $merged['interpreterConfig']['type']);
    }

    /** the mapping list is one value: a row edited meanwhile refuses a proposal changing another */
    public function testALaterEditOfAMappingRowRefusesAProposalChangingAnother(): void
    {
        $base = $this->stored();
        $mappings = $base['mappingConfig'];
        $mappings[1]['label'] = 'Model name';
        $proposed = $this->propose($base, ['mappingConfig' => $mappings]);
        $main = $base;
        $main['mappingConfig'][0]['label'] = 'VIN number';

        $conflicts = $this->merge->conflictsDeep($base, $main, $this->merge->deltaOf($base, $proposed));

        static::assertSame(['mappingConfig'], array_column($conflicts, 'path'));
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function propose(array $stored, array $document): array
    {
        $existing = $this->createStub(Configuration::class);
        $existing->method('getConfiguration')->willReturn($stored);
        $existing->method('getType')->willReturn(ImportConfigPolicy::CONFIG_TYPE);

        $builder = new ImportConfigProposalBuilder(new ImportConfigVocabulary([]));

        return $builder->build('dealer-feed', $document, $existing);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        return [
            'general' => [
                'name' => 'dealer-feed',
                'type' => ImportConfigPolicy::CONFIG_TYPE,
                'active' => true,
                'path' => '',
            ],
            'loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '/feeds/cars.json']],
            'interpreterConfig' => ['type' => 'json'],
            'resolverConfig' => [
                'dataObjectClassId' => 'CAR',
                'loadingStrategy' => ['type' => 'notLoad'],
                'createLocationStrategy' => ['type' => 'staticPath', 'settings' => ['path' => '/import']],
                'locationUpdateStrategy' => ['type' => 'noChange'],
                'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
            ],
            'mappingConfig' => [
                [
                    'label' => 'VIN',
                    'dataSourceIndex' => ['vin'],
                    'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'vin']],
                ],
                [
                    'label' => 'Model',
                    'dataSourceIndex' => ['model'],
                    'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'name']],
                ],
            ],
        ];
    }
}
