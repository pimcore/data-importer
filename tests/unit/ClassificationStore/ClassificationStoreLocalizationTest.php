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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ClassificationStore;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Classificationstore;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;
use Pimcore\Tool;

/**
 * Which language a classification store key lives in, as both data targets, the key name endpoint and the
 * attributes endpoint answer it: the key's own flag where it states one, the field's where it does not.
 */
class ClassificationStoreLocalizationTest extends Unit
{
    private const STORE = 'diLocStore';

    private const STORE_PLAIN = 'diLocPlainStore';

    private const GROUP = 'diLocGroup';

    private const GROUP_PLAIN = 'diLocPlainGroup';

    private const CLASS_NAME = 'DiCsLocClass';

    private const CLASS_NAME_PLAIN = 'DiCsLocPlainClass';

    private const KEY_TRUE = 'diLocTrue';

    private const KEY_FALSE = 'diLocFalse';

    private const KEY_FLAGLESS = 'diLocFlagless';

    private const KEY_PLAIN = 'diLocPlain';

    private const KEY_LIST = 'diLocList';

    private const KEY_VARIANT = 'diLocVariant';

    private const KEY_LIST_LOCALIZED = 'diLocListLocalized';

    private const KEY_VARIANT_LOCALIZED = 'diLocVariantLocalized';

    private const VARIANT_METRIC = 'metric';

    private const TYPE_INPUT = 'input';

    private const TYPE_MULTI_VALUE = 'multiValue';

    private const TARGET_SINGLE = 'single';

    private const TARGET_BATCH = 'batch';

    private const TARGETS = [self::TARGET_SINGLE, self::TARGET_BATCH];

    private const VALUE = 'value';

    private const VALUE_LOCALIZED = 'localized';

    private const VALUE_PLAIN = 'plain';

    private int $groupId;

    private string $language;

    private string $classId;

    private string $plainClassId;

    /**
     * @var array<string, int>
     */
    private array $keyIds = [];

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE, 'document');
        $group = $fixture->group($store->getId(), self::GROUP);

        $list = new MultiValue();
        $list->setName(self::KEY_LIST);
        $list->setDelegateDatatype(self::TYPE_INPUT);
        $list->setMultiValue(true);

        $variant = new MultiValue();
        $variant->setName(self::KEY_VARIANT);
        $variant->setDelegateDatatype(self::TYPE_INPUT);
        $variant->setVariants([self::VARIANT_METRIC]);

        $listLocalized = clone $list;
        $listLocalized->setName(self::KEY_LIST_LOCALIZED);

        $variantLocalized = clone $variant;
        $variantLocalized->setName(self::KEY_VARIANT_LOCALIZED);

        $keys = [
            self::KEY_TRUE => [self::TYPE_INPUT, null, true],
            self::KEY_FALSE => [self::TYPE_INPUT, null, false],
            self::KEY_FLAGLESS => [self::TYPE_INPUT, null, null],
            self::KEY_LIST => [self::TYPE_MULTI_VALUE, $list, false],
            self::KEY_VARIANT => [self::TYPE_MULTI_VALUE, $variant, false],
            self::KEY_LIST_LOCALIZED => [self::TYPE_MULTI_VALUE, $listLocalized, true],
            self::KEY_VARIANT_LOCALIZED => [self::TYPE_MULTI_VALUE, $variantLocalized, true],
        ];
        $sorter = 1;
        foreach ($keys as $name => [$type, $definition, $localized]) {
            $key = $fixture->key($store->getId(), $name, $type, $definition, null, $localized);
            $fixture->relate($group->getId(), $key->getId(), $sorter++);
            $this->keyIds[$name] = $key->getId();
        }

        $plainStore = $fixture->store(self::STORE_PLAIN, 'document');
        $plainGroup = $fixture->group($plainStore->getId(), self::GROUP_PLAIN);
        $plainKey = $fixture->key($plainStore->getId(), self::KEY_PLAIN, self::TYPE_INPUT);
        $fixture->relate($plainGroup->getId(), $plainKey->getId());

        $this->classId = $fixture->classDefinition(self::CLASS_NAME, $store->getId())->getId();
        $this->plainClassId = $fixture->classDefinition(self::CLASS_NAME_PLAIN, $plainStore->getId())->getId();
        $this->groupId = $group->getId();
        $this->language = (string)(Tool::getValidLanguages()[0] ?? 'en');
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testTheKeyNameEndpointReportsWhetherTheKeyIsLocalizedInTheField(): void
    {
        $expected = [
            [self::KEY_TRUE, ClassificationStoreFixture::FIELD_NON_LOCALIZED, true],
            [self::KEY_FALSE, ClassificationStoreFixture::FIELD_LOCALIZED, false],
            [self::KEY_FLAGLESS, ClassificationStoreFixture::FIELD_LOCALIZED, true],
            [self::KEY_FLAGLESS, ClassificationStoreFixture::FIELD_NON_LOCALIZED, false],
        ];

        /** @var DataTypeServiceInterface $service */
        $service = $this->tester->grabService(DataTypeServiceInterface::class);
        foreach ($expected as [$keyName, $field, $localized]) {
            $response = $service->loadClassificationStoreKeyName(
                $this->groupId . '-' . $this->keyIds[$keyName],
                $this->classId,
                $field
            );

            static::assertSame($localized, $response->getLocalized(), $keyName . ' in ' . $field);
        }
    }

    public function testTheAttributesEndpointOffersALanguageWhereAnyKeyOfTheStoreIsLocalized(): void
    {
        /** @var DataTypeServiceInterface $service */
        $service = $this->tester->grabService(DataTypeServiceInterface::class);
        $flags = fn (string $classId): array => array_column(
            $service->loadClassificationStoreAttributes($classId)->getAttributes(),
            'localized',
            'key'
        );

        $plain = $flags($this->plainClassId);
        static::assertFalse($plain[ClassificationStoreFixture::FIELD_NON_LOCALIZED], 'no localized key');
        static::assertTrue($plain[ClassificationStoreFixture::FIELD_LOCALIZED], 'localized field');

        $withALocalizedKey = $flags($this->classId);
        static::assertTrue($withALocalizedKey[ClassificationStoreFixture::FIELD_NON_LOCALIZED], 'one localized key');
    }

    public function testAKeyLocalizedInsideANonLocalizedFieldIsWrittenUnderTheChosenLanguage(): void
    {
        foreach (self::TARGETS as $targetKind) {
            $element = $this->newElement();
            $field = ClassificationStoreFixture::FIELD_NON_LOCALIZED;
            $this->write($targetKind, $element, self::KEY_TRUE, self::VALUE, $field);

            $this->assertValue(self::VALUE, $element, self::KEY_TRUE, $this->language, $field, $targetKind);
            $this->assertValue(null, $element, self::KEY_TRUE, null, $field, $targetKind);

            $element->save();
            $reloaded = $this->reload($element);
            $this->assertValue(self::VALUE, $reloaded, self::KEY_TRUE, $this->language, $field, $targetKind);
            $this->assertValue(null, $reloaded, self::KEY_TRUE, null, $field, $targetKind);
        }
    }

    public function testAKeyNotLocalizedInsideALocalizedFieldIgnoresAConfiguredLanguage(): void
    {
        foreach (self::TARGETS as $targetKind) {
            $element = $this->newElement();
            $this->write($targetKind, $element, self::KEY_FALSE, self::VALUE);

            $this->assertValue(self::VALUE, $element, self::KEY_FALSE, null, null, $targetKind);
            $this->assertValue(null, $element, self::KEY_FALSE, $this->language, null, $targetKind);

            $element->save();
            $reloaded = $this->reload($element);
            $this->assertValue(self::VALUE, $reloaded, self::KEY_FALSE, null, null, $targetKind);
            $this->assertValue(null, $reloaded, self::KEY_FALSE, $this->language, null, $targetKind);
        }
    }

    public function testAKeyStatingNoFlagInheritsTheFlagOfTheField(): void
    {
        foreach (self::TARGETS as $targetKind) {
            $element = $this->newElement();
            $this->write($targetKind, $element, self::KEY_FLAGLESS, self::VALUE_LOCALIZED);
            $this->assertValue(self::VALUE_LOCALIZED, $element, self::KEY_FLAGLESS, $this->language, null, $targetKind);
            $this->assertValue(null, $element, self::KEY_FLAGLESS, null, null, $targetKind);

            $field = ClassificationStoreFixture::FIELD_NON_LOCALIZED;
            $this->write($targetKind, $element, self::KEY_FLAGLESS, self::VALUE_PLAIN, $field);
            $this->assertValue(self::VALUE_PLAIN, $element, self::KEY_FLAGLESS, null, $field, $targetKind);
        }
    }

    public function testAQualifiedWriteAddressesTheBucketOfAKeyThatIsNotLocalized(): void
    {
        $element = $this->newElement();
        $store = $this->store($element);

        $this->write(self::TARGET_SINGLE, $element, self::KEY_LIST, json_encode(['a', 'b']));
        $listId = $this->keyIds[self::KEY_LIST];
        static::assertSame(['a', 'b'], array_values($store->getKeyValues($this->groupId, $listId)));
        static::assertSame([], $store->getKeyValues($this->groupId, $listId, $this->language));

        $this->write(self::TARGET_SINGLE, $element, self::KEY_VARIANT, 'v', null, '#' . self::VARIANT_METRIC);
        $keyId = $this->keyIds[self::KEY_VARIANT];
        static::assertSame(
            ['v'],
            array_values($store->getKeyValues($this->groupId, $keyId, null, self::VARIANT_METRIC))
        );
        static::assertSame([], $store->getKeyValues($this->groupId, $keyId, $this->language, self::VARIANT_METRIC));
    }

    public function testAQualifiedWriteCarriesTheConfiguredLanguageForAKeyThatIsLocalized(): void
    {
        $element = $this->newElement();
        $field = ClassificationStoreFixture::FIELD_NON_LOCALIZED;
        $store = $this->store($element, $field);

        $this->write(self::TARGET_SINGLE, $element, self::KEY_LIST_LOCALIZED, json_encode(['a', 'b']), $field);
        $listId = $this->keyIds[self::KEY_LIST_LOCALIZED];
        static::assertSame(['a', 'b'], array_values($store->getKeyValues($this->groupId, $listId, $this->language)));
        static::assertSame([], $store->getKeyValues($this->groupId, $listId));

        $suffix = '#' . self::VARIANT_METRIC;
        $this->write(self::TARGET_SINGLE, $element, self::KEY_VARIANT_LOCALIZED, 'v', $field, $suffix);
        $keyId = $this->keyIds[self::KEY_VARIANT_LOCALIZED];
        static::assertSame(
            ['v'],
            array_values($store->getKeyValues($this->groupId, $keyId, $this->language, self::VARIANT_METRIC))
        );
        static::assertSame([], $store->getKeyValues($this->groupId, $keyId, null, self::VARIANT_METRIC));
    }

    public function testOneBatchEntryWritesEachKeyUnderTheBucketItResolvesTo(): void
    {
        $element = $this->newElement();
        $target = $this->batchTarget(ClassificationStoreFixture::FIELD_NON_LOCALIZED);

        $target->assignData($element, [
            $this->reference(self::KEY_TRUE) => self::VALUE_LOCALIZED,
            $this->reference(self::KEY_FALSE) => self::VALUE_PLAIN,
        ]);

        $field = ClassificationStoreFixture::FIELD_NON_LOCALIZED;
        $batch = self::TARGET_BATCH;
        $this->assertValue(self::VALUE_LOCALIZED, $element, self::KEY_TRUE, $this->language, $field, $batch);
        $this->assertValue(null, $element, self::KEY_TRUE, null, $field, $batch);
        $this->assertValue(self::VALUE_PLAIN, $element, self::KEY_FALSE, null, $field, $batch);
        $this->assertValue(null, $element, self::KEY_FALSE, $this->language, $field, $batch);
    }

    public function testALocalizedKeyWrittenWithoutALanguageGoesToTheDefaultBucketAndWarnsOncePerKey(): void
    {
        $field = ClassificationStoreFixture::FIELD_NON_LOCALIZED;

        foreach (self::TARGETS as $targetKind) {
            $logger = $this->createMock(ApplicationLogger::class);
            $logger->expects(static::once())->method('warning')->with(
                static::stringContains('is localized, but the mapping entry carries no language')
            );
            $resolver = (new ClassificationStoreFixture())->localizationResolver();
            $settings = ['fieldName' => $field];

            if ($targetKind === self::TARGET_BATCH) {
                $target = new ClassificationstoreBatch($logger, $resolver);
                $target->setSettings($settings);
                $assign = fn (Concrete $element, string $value) => $target->assignData(
                    $element,
                    [$this->reference(self::KEY_TRUE) => $value]
                );
            } else {
                $target = new Classificationstore($logger, $resolver);
                $target->setSettings($settings + ['keyId' => $this->reference(self::KEY_TRUE)]);
                $assign = fn (Concrete $element, string $value) => $target->assignData($element, $value);
            }

            $first = $this->newElement();
            $assign($first, 'one');
            $second = $this->newElement();
            $assign($second, 'two');

            $this->assertValue('one', $first, self::KEY_TRUE, null, $field, $targetKind);
            $this->assertValue(null, $first, self::KEY_TRUE, $this->language, $field, $targetKind);
            $this->assertValue('two', $second, self::KEY_TRUE, null, $field, $targetKind);
        }
    }

    private function reference(string $keyName): string
    {
        return $this->groupId . '-' . $this->keyIds[$keyName];
    }

    private function newElement(): Concrete
    {
        return (new ClassificationStoreFixture())->newObject(self::CLASS_NAME, uniqid('loc', true));
    }

    private function reload(Concrete $element): Concrete
    {
        $reloaded = Concrete::getById((int)$element->getId(), ['force' => true]);
        static::assertInstanceOf(Concrete::class, $reloaded);

        return $reloaded;
    }

    private function store(
        Concrete $element,
        string $field = ClassificationStoreFixture::FIELD_LOCALIZED
    ): ClassificationstoreModel {
        $getter = 'get' . ucfirst($field);
        $store = $element->$getter();
        static::assertInstanceOf(ClassificationstoreModel::class, $store);

        return $store;
    }

    private function assertValue(
        mixed $expected,
        Concrete $element,
        string $keyName,
        ?string $language,
        ?string $field,
        string $message
    ): void {
        $store = $this->store($element, $field ?? ClassificationStoreFixture::FIELD_LOCALIZED);

        static::assertSame(
            $expected,
            $store->getLocalizedKeyValue($this->groupId, $this->keyIds[$keyName], $language ?? 'default', true, true),
            $message . ': ' . $keyName . ' under ' . ($language ?? 'no language')
        );
    }

    private function batchTarget(string $field): ClassificationstoreBatch
    {
        $target = new ClassificationstoreBatch(
            $this->createMock(ApplicationLogger::class),
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings(['fieldName' => $field, 'language' => $this->language]);

        return $target;
    }

    /**
     * Writes one value of one key through the named target, always with a configured language.
     */
    private function write(
        string $targetKind,
        Concrete $element,
        string $keyName,
        mixed $value,
        ?string $field = null,
        string $suffix = ''
    ): void {
        $field ??= ClassificationStoreFixture::FIELD_LOCALIZED;
        $reference = $this->reference($keyName) . $suffix;

        if ($targetKind === self::TARGET_BATCH) {
            $this->batchTarget($field)->assignData($element, [$reference => $value]);

            return;
        }

        $target = new Classificationstore(
            $this->createMock(ApplicationLogger::class),
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings(['fieldName' => $field, 'keyId' => $reference, 'language' => $this->language]);
        $target->assignData($element, $value);
    }
}
