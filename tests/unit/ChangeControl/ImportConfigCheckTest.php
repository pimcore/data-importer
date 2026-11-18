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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Unit\ChangeControl;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ImportConfigCheck;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\AssetLoader;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ImportConfigCheckFixture;

class ImportConfigCheckTest extends Unit
{
    private const string FIRST_TARGET = 'mappingConfig.0.dataTarget';

    private const string FIRST_FIELD = 'mappingConfig.0.dataTarget.settings.fieldName';

    private const string CREATE_LOCATION = 'resolverConfig.createLocationStrategy';

    private const string LOADING = 'resolverConfig.loadingStrategy';

    private const string EXECUTION = 'executionConfig';

    private const string CRON = 'executionConfig.cronDefinition';

    private const string JOB = 'job';

    private const string LOADER = 'loaderConfig';

    private const string INTERPRETER = 'interpreterConfig';

    private const string MAPPING = 'mappingConfig';

    private const string CLASS_ID_PATH = 'resolverConfig.dataObjectClassId';

    private const string NOT_A_FIELD = 'mappingConfig[%d].dataTarget.settings.fieldName "%s" is not a field of class '
        . ImportConfigCheckFixture::CLASS_NAME . '.';

    private const string MISSING_FIELD = 'doesNotExist';

    private const string TARGET_FIELD = 'dataTarget.settings.fieldName';

    private const array CONFIGURATION = [
        'general' => ['name' => 'dealer-feed', 'active' => true],
        // neither the file nor the folder below exist yet: an import may only find them later
        'loaderConfig' => ['type' => 'asset', 'settings' => ['assetPath' => '/feeds/not-uploaded-yet.json']],
        'interpreterConfig' => ['type' => 'json', 'settings' => ['path' => '']],
        'resolverConfig' => [
            'dataObjectClassId' => ImportConfigCheckFixture::CLASS_ID,
            'loadingStrategy' => ['type' => 'notLoad'],
            'createLocationStrategy' => ['type' => 'staticPath', 'settings' => ['path' => '/import/not/created/yet']],
            'locationUpdateStrategy' => ['type' => 'noChange'],
            'publishingStrategy' => ['type' => 'noChangeUnpublishNew'],
        ],
        'processingConfig' => [
            'executionType' => 'sequential',
            'idDataIndex' => '',
            'cleanup' => ['doCleanup' => false],
        ],
        'mappingConfig' => [[
            'label' => 'VIN',
            'dataSourceIndex' => ['vin'],
            'transformationPipeline' => [['type' => 'trim', 'settings' => ['mode' => 'both']]],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'vin']],
        ]],
        'executionConfig' => ['scheduleType' => 'cron', 'cronDefinition' => ''],
    ];

    private ImportConfigCheck $check;

    protected function _before(): void
    {
        $this->check = ImportConfigCheckFixture::check(['asset' => static fn (): AssetLoader => new AssetLoader()]);
    }

    protected function _after(): void
    {
        ImportConfigCheckFixture::forget();
    }

    public function testACompleteConfigurationIsNoProblem(): void
    {
        static::assertSame([], $this->check->problems(self::CONFIGURATION, null));
    }

    /** an empty mapping, an empty label, extra stored keys and switching on are the review's to judge */
    public function testWhatTheReviewDecidesIsNoProblem(): void
    {
        $state = self::with(self::CONFIGURATION, 'mappingConfig.0.label', '');
        $state = self::with($state, 'general.vendorFlag', 'kept');
        $state = self::with($state, 'resolverConfig.vendorOption', ['kept' => true]);
        static::assertSame([], $this->check->problems($state, null));

        static::assertSame([], $this->check->problems(self::with(self::CONFIGURATION, self::MAPPING, []), null));
    }

    public function testAnXmlInterpreterWithoutItsXPathIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::INTERPRETER, ['type' => 'xml', 'settings' => []]);

        static::assertSame(['interpreterConfig: Empty XPath.'], $this->check->problems($state, null));
    }

    public function testAnInterpreterTheInstallationLacksIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::INTERPRETER, ['type' => 'vendor.format']);

        static::assertSame(
            ['interpreterConfig: Unknown loader type `vendor.format`'],
            $this->check->problems($state, null)
        );
    }

    public function testAStaticPathWithoutItsPathIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::CREATE_LOCATION, ['type' => 'staticPath', 'settings' => []]);

        static::assertSame(['resolverConfig: Empty path.'], $this->check->problems($state, null));
    }

    public function testAnAttributeStrategyWithoutItsAttributeIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::LOADING, [
            'type' => 'attribute',
            'settings' => ['dataSourceIndex' => 'vin'],
        ]);

        static::assertSame(['resolverConfig: Empty attribute name.'], $this->check->problems($state, null));
    }

    public function testALoadingStrategyWithoutItsDataSourceIndexIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::LOADING, ['type' => 'id', 'settings' => []]);

        static::assertSame(['resolverConfig: Empty data source index.'], $this->check->problems($state, null));
    }

    public function testAClassTheInstallationLacksIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::CLASS_ID_PATH, 'DI_NO_SUCH_CLASS');

        static::assertSame(
            ['resolverConfig.dataObjectClassId "DI_NO_SUCH_CLASS" is not a data object class here.'],
            $this->check->problems($state, null)
        );
    }

    public function testADirectTargetWithoutItsFieldNameIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::FIRST_TARGET, ['type' => 'direct', 'settings' => []]);

        static::assertSame(['mappingConfig[0]: Empty field name.'], $this->check->problems($state, null));
    }

    public function testAMappingEntryWithoutADataTargetIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::FIRST_TARGET, null);

        static::assertSame(
            ['mappingConfig[0].dataTarget.type is missing: every mapping entry needs a data target.'],
            $this->check->problems($state, null)
        );
    }

    public function testAMappingEntryThatIsNoObjectIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, 'mappingConfig.1', 'vin');

        static::assertSame(
            ['mappingConfig[1] must be a mapping entry: an object with a dataSourceIndex and a dataTarget.'],
            $this->check->problems($state, null)
        );
    }

    /**
     * @dataProvider missingFields
     */
    public function testAFieldTheClassLacksIsAProblem(string $fieldName): void
    {
        $state = self::with(self::CONFIGURATION, self::FIRST_FIELD, $fieldName);

        static::assertSame([sprintf(self::NOT_A_FIELD, 0, $fieldName)], $this->check->problems($state, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingFields(): array
    {
        return [
            'no such field' => [self::MISSING_FIELD],
            'no such brick field' => ['bricks.' . ImportConfigCheckFixture::BRICK_NAME . '.torque'],
            'a brick the container does not allow' => ['bricks.DiOtherBrick.power'],
            'a field that holds no bricks' => ['vin.' . ImportConfigCheckFixture::BRICK_NAME . '.power'],
            'a path the importer cannot split' => ['bricks.power'],
        ];
    }

    /**
     * @dataProvider writableFields
     */
    public function testAFieldTheImporterCanWriteIsNoProblem(string $fieldName): void
    {
        $state = self::with(self::CONFIGURATION, self::FIRST_FIELD, $fieldName);

        static::assertSame([], $this->check->problems($state, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function writableFields(): array
    {
        return [
            'an own field' => ['name'],
            'a localized field' => ['description'],
            'a brick field' => ['bricks.' . ImportConfigCheckFixture::BRICK_NAME . '.power'],
            'the key' => ['key'],
            'the path' => ['path'],
            'another setter of the object' => ['published'],
        ];
    }

    public function testAClassificationStoreTargetNeedsAClassificationStoreField(): void
    {
        $target = ['type' => 'classificationstore', 'settings' => [
            'fieldName' => 'attributes',
            'keyId' => '1-2',
            'language' => '',
        ]];
        $state = self::with(self::CONFIGURATION, self::FIRST_TARGET, $target);
        static::assertSame([], $this->check->problems($state, null));

        static::assertSame(
            ['mappingConfig[0].dataTarget.settings.fieldName "vin" is not a classification store field of class '
                . ImportConfigCheckFixture::CLASS_NAME . '.'],
            $this->check->problems(self::with($state, self::FIRST_FIELD, 'vin'), null)
        );
    }

    public function testACronDefinitionTheSchedulerCannotReadIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::CRON, 'every night');

        static::assertSame(
            ['executionConfig.cronDefinition "every night" is not a cron expression like "0 3 * * *".'],
            $this->check->problems($state, null)
        );
        static::assertSame(
            [],
            $this->check->problems(self::with(self::CONFIGURATION, self::CRON, '0 3 * * *'), null)
        );
    }

    /**
     * @dataProvider unreadableDates
     */
    public function testAScheduledDateTheSchedulerCannotReadIsAProblem(string $scheduledAt): void
    {
        $state = self::with(self::CONFIGURATION, self::EXECUTION, [
            'scheduleType' => self::JOB,
            'scheduledAt' => $scheduledAt,
        ]);

        static::assertSame(
            [sprintf(
                'executionConfig.scheduledAt "%s" is not a date and time like "31-12-2026 23:30" (d-m-Y H:i).',
                $scheduledAt,
            )],
            $this->check->problems($state, null)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableDates(): array
    {
        return [
            'another format' => ['2026-12-31 23:30'],
            'a day that does not exist' => ['32-13-2026 23:30'],
        ];
    }

    public function testAScheduledDateTheSchedulerReadsIsNoProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::EXECUTION, [
            'scheduleType' => self::JOB,
            'scheduledAt' => '31-12-2026 23:30',
        ]);

        static::assertSame([], $this->check->problems($state, null));
    }

    public function testACleanupStrategyTheInstallationLacksIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, 'processingConfig.cleanup', [
            'doCleanup' => true,
            'strategy' => 'archive',
        ]);

        static::assertSame(
            ['processingConfig.cleanup.strategy "archive" is not a cleanup strategy here.'],
            $this->check->problems($state, null)
        );
    }

    public function testALoaderTheInstallationLacksIsAProblem(): void
    {
        $state = self::with(self::CONFIGURATION, self::LOADER, ['type' => 'vendor.feed']);

        static::assertSame(
            ['loaderConfig.type: "vendor.feed" is not a loader here.'],
            $this->check->problems($state, null)
        );
    }

    public function testASectionThatIsNoObjectIsAProblem(): void
    {
        static::assertSame(
            ['loaderConfig must be an object.'],
            $this->check->problems(self::with(self::CONFIGURATION, self::LOADER, 'asset'), null)
        );
        static::assertSame(
            ['mappingConfig must be a list of mapping entries, one per column.'],
            $this->check->problems(self::with(self::CONFIGURATION, self::MAPPING, 'vin'), null)
        );
    }

    /** a stored section the update leaves alone is not the update's to refuse */
    public function testAnUpdateIsJudgedOnlyWhereItChangesTheStoredConfiguration(): void
    {
        $stored = self::with(self::CONFIGURATION, self::INTERPRETER, ['type' => 'xml', 'settings' => []]);
        $stored = self::with($stored, self::FIRST_FIELD, self::MISSING_FIELD);
        $stored = self::with($stored, self::CRON, 'every night');
        $stored = self::with($stored, 'mappingConfig.0.mappingId', 'm-vin');

        static::assertSame([], $this->check->problems(self::with($stored, 'general.active', false), $stored));

        // the stored row is matched without the id the editor minted; the row copied from it is new
        $copy = self::with(self::CONFIGURATION[self::MAPPING][0], self::TARGET_FIELD, self::MISSING_FIELD);
        $state = self::with($stored, 'mappingConfig.0', self::with($stored[self::MAPPING][0], 'mappingId', 'm-new'));
        $state = self::with($state, 'mappingConfig.1', self::with($copy, 'label', 'VIN again'));

        static::assertSame(
            [sprintf(self::NOT_A_FIELD, 1, self::MISSING_FIELD)],
            $this->check->problems($state, $stored)
        );
    }

    public function testAnUpdateThatChangesTheClassJudgesEveryRowAgain(): void
    {
        $stored = self::with(self::CONFIGURATION, self::CLASS_ID_PATH, 'DI_OTHER_CLASS');
        $stored = self::with($stored, self::FIRST_FIELD, self::MISSING_FIELD);
        $state = self::with($stored, self::CLASS_ID_PATH, ImportConfigCheckFixture::CLASS_ID);

        static::assertSame(
            [sprintf(self::NOT_A_FIELD, 0, self::MISSING_FIELD)],
            $this->check->problems($state, $stored)
        );
    }

    /**
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    private static function with(array $state, string $path, mixed $value): array
    {
        $node = &$state;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node)) {
                $node = [];
            }
            $node = &$node[$segment];
        }
        $node = $value;

        return $state;
    }
}
