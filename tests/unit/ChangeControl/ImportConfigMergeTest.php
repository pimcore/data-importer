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
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigPolicy;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigProposalBuilder;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ImportConfigVocabulary;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ImportConfigCheckFixture;

/**
 * Approval merges a proposal over the configuration as stored then, against the state stored
 * when it was proposed: an edit made in between survives or refuses the approval, never lost.
 */
class ImportConfigMergeTest extends Unit
{
    /** an XML interpreter the importer can load */
    private const array XML_INTERPRETER = ['type' => 'xml', 'settings' => ['xpath' => '/cars/car', 'schema' => '']];

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
        $proposed = $this->propose($base, ['interpreterConfig' => self::XML_INTERPRETER]);
        $main = $base;
        $main['interpreterConfig']['type'] = 'csv';

        $conflicts = $this->merge->conflictsDeep($base, $main, $this->merge->deltaOf($base, $proposed));

        static::assertSame(['interpreterConfig.type'], array_column($conflicts, 'path'));
    }

    public function testALaterEditOfAnotherFieldSurvivesTheApproval(): void
    {
        $base = $this->stored();
        $proposed = $this->propose($base, ['interpreterConfig' => self::XML_INTERPRETER]);
        $main = $base;
        $main['loaderConfig']['settings']['assetPath'] = '/feeds/trucks.json';
        $delta = $this->merge->deltaOf($base, $proposed);

        $merged = $this->merge->deepOverlay($main, $delta);

        static::assertSame([], $this->merge->conflictsDeep($base, $main, $delta));
        static::assertSame('/feeds/trucks.json', $merged['loaderConfig']['settings']['assetPath']);
        static::assertSame('xml', $merged['interpreterConfig']['type']);
    }

    /** approving a switch to another loader stores the new loader's settings, not the old loader's path */
    public function testApprovingALoaderSwitchStoresNoSettingOfThePreviousLoader(): void
    {
        $base = $this->stored();
        $proposed = $this->propose($base, [
            'loaderConfig' => [
                'type' => 'http',
                'settings' => ['schema' => 'https://', 'url' => 'example.com/cars.json'],
            ],
        ]);
        $delta = $this->merge->deltaOf($base, $proposed);

        $merged = $this->merge->deepOverlay($base, $delta);

        static::assertSame([], $this->merge->conflictsDeep($base, $base, $delta));
        static::assertSame('http', $merged['loaderConfig']['type']);
        static::assertSame('https://', $merged['loaderConfig']['settings']['schema']);
        static::assertSame('example.com/cars.json', $merged['loaderConfig']['settings']['url']);
        // Change Control marks a removed key with null; the old path itself is gone
        static::assertNull($merged['loaderConfig']['settings']['assetPath'] ?? null);
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
     * The Studio editor's save of another field mints a mappingId on every row stored without
     * one: the approval stores the proposed row, keeps the editor's edit and the minted ids.
     */
    public function testAnEditorSaveThatOnlyAddsRowIdsDoesNotRefuseTheApproval(): void
    {
        $policy = $this->policy();
        $stored = $this->stored();
        $base = $policy->comparable($stored);
        $mappings = $stored['mappingConfig'];
        $mappings[1]['dataTarget']['settings']['language'] = 'fr';
        $proposed = $this->propose($stored, ['mappingConfig' => $mappings]);

        $edited = $stored;
        $edited['general']['description'] = 'Edited in the Studio editor';
        $edited['mappingConfig'][0]['mappingId'] = 'm-vin';
        $edited['mappingConfig'][1]['mappingId'] = 'm-model';
        $main = $policy->comparable($edited);
        $delta = $this->merge->deltaOf($base, $proposed);

        static::assertSame([], $this->merge->conflictsDeep($base, $main, $delta));

        $saved = $policy->restored($this->merge->deepOverlay($main, $delta), $edited);
        static::assertSame('Edited in the Studio editor', $saved['general']['description']);
        static::assertSame('fr', $saved['mappingConfig'][1]['dataTarget']['settings']['language']);
        static::assertSame(['m-vin', 'm-model'], array_column($saved['mappingConfig'], 'mappingId'));
    }

    public function testAnEditorSaveThatChangesAMappingRowStillRefusesTheApproval(): void
    {
        $policy = $this->policy();
        $stored = $this->stored();
        $base = $policy->comparable($stored);
        $mappings = $stored['mappingConfig'];
        $mappings[1]['dataTarget']['settings']['language'] = 'fr';
        $proposed = $this->propose($stored, ['mappingConfig' => $mappings]);

        $edited = $stored;
        $edited['mappingConfig'][0]['mappingId'] = 'm-vin';
        $edited['mappingConfig'][1]['mappingId'] = 'm-model';
        $edited['mappingConfig'][0]['label'] = 'VIN number';

        $conflicts = $this->merge->conflictsDeep(
            $base,
            $policy->comparable($edited),
            $this->merge->deltaOf($base, $proposed),
        );

        static::assertSame(['mappingConfig'], array_column($conflicts, 'path'));
    }

    /** a proposal that keeps the stored ids, as the agent is asked to, compares as one without them */
    public function testAProposalIsRecordedWithoutTheStoredRowIds(): void
    {
        $stored = $this->stored();
        $stored['mappingConfig'][0]['mappingId'] = 'm-vin';

        $proposed = $this->propose($stored, ['mappingConfig' => $stored['mappingConfig']]);

        static::assertArrayNotHasKey('mappingId', $proposed['mappingConfig'][0]);
        static::assertSame([], $this->merge->deltaOf($this->policy()->comparable($stored), $proposed));
    }

    /**
     * A row the proposal rewrites keeps the id the agent sent: guessing by label, source or position
     * would give it the id of a row the proposal deleted.
     */
    public function testARewrittenRowKeepsTheIdTheProposalSentWhenAnotherRowIsDeleted(): void
    {
        $policy = $this->policy();
        $stored = $this->stored();
        $stored['mappingConfig'][0]['mappingId'] = 'm-vin';
        $stored['mappingConfig'][1]['mappingId'] = 'm-model';
        $rewritten = $stored['mappingConfig'][1];
        $rewritten['label'] = 'Mileage';
        $rewritten['dataSourceIndex'] = ['mileage'];

        $proposed = $this->propose($stored, ['mappingConfig' => [$rewritten]]);
        $base = $policy->comparable($stored);
        $saved = $policy->restored($this->merge->deepOverlay($base, $this->merge->deltaOf($base, $proposed)), $stored);

        $labelsAndIds = array_map(
            static fn (array $row): array => [$row['label'], $row['mappingId'] ?? null],
            $saved['mappingConfig'],
        );
        static::assertSame([['Mileage', 'm-model']], $labelsAndIds);
    }

    private function policy(): ImportConfigPolicy
    {
        return new ImportConfigPolicy(
            $this->createStub(ConfigurationServiceInterface::class),
            ImportConfigCheckFixture::check(),
            $this->createStub(StoredConfigurationsInterface::class),
        );
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

        $builder = new ImportConfigProposalBuilder(
            new ImportConfigVocabulary([]),
            ImportConfigCheckFixture::check(),
        );

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
