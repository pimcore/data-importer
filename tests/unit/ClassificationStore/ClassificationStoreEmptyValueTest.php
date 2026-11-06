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
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Classificationstore;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * `writeIfSourceIsEmpty` on both classification store data targets: in force behind the opt-in
 * marker, today's behaviour without it, and a configuration carrying no `language` at all.
 */
class ClassificationStoreEmptyValueTest extends Unit
{
    private const STORE = 'diEmptyStore';

    private const GROUP = 'diEmptyGroup';

    private const KEY = 'diEmptyInput';

    private const CLASS_NAME = 'DiCsEmptyClass';

    private const MARKER = 'writeIfSourceIsEmptyConfigured';

    private const PRESET = 'preset';

    private const IMPORTED = 'imported';

    /**
     * The seven values the shared empty test treats as empty. `0` among them is the known wart,
     * accepted as the migration-safe choice.
     *
     * @var array<string, mixed>
     */
    private const EMPTY_VALUES = [
        'null' => null,
        'false' => false,
        'int zero' => 0,
        'float zero' => 0.0,
        'string zero' => '0',
        'empty string' => '',
        'empty array' => [],
    ];

    private int $groupId;

    private int $keyId;

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE);
        $group = $fixture->group($store->getId(), self::GROUP);
        $key = $fixture->key($store->getId(), self::KEY, 'input');
        $fixture->relate($group->getId(), $key->getId());
        $fixture->classDefinition(self::CLASS_NAME, $store->getId());

        $this->groupId = $group->getId();
        $this->keyId = $key->getId();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testMarkedEntryDoesNotWriteAnEmptyValueWhenTheSettingIsOff(): void
    {
        foreach (self::EMPTY_VALUES as $label => $value) {
            $single = $this->singleKeyTarget([self::MARKER => true, 'writeIfSourceIsEmpty' => false]);
            $element = $this->objectWithPresetValue();
            $single->assignData($element, $value);
            static::assertSame(self::PRESET, $this->keyValue($element), 'single key target, ' . $label);

            $batch = $this->batchTarget([self::MARKER => true, 'writeIfSourceIsEmpty' => false]);
            $element = $this->objectWithPresetValue();
            $batch->assignData($element, [$this->batchKey() => $value]);
            static::assertSame(self::PRESET, $this->keyValue($element), 'batch target, ' . $label);
        }
    }

    public function testMarkedEntryWritesAnEmptyValueWhenTheSettingIsOn(): void
    {
        foreach (self::EMPTY_VALUES as $label => $value) {
            $single = $this->singleKeyTarget([self::MARKER => true]);
            $element = $this->objectWithPresetValue();
            $single->assignData($element, $value);
            static::assertNotSame(self::PRESET, $this->keyValue($element), 'single key target, ' . $label);

            $batch = $this->batchTarget([self::MARKER => true]);
            $element = $this->objectWithPresetValue();
            $batch->assignData($element, [$this->batchKey() => $value]);
            static::assertNotSame(self::PRESET, $this->keyValue($element), 'batch target, ' . $label);
        }
    }

    public function testMarkedEntryWritesANonEmptyValueWhateverTheSettingSays(): void
    {
        foreach ([true, false] as $writeIfSourceIsEmpty) {
            $single = $this->singleKeyTarget([
                self::MARKER => true,
                'writeIfSourceIsEmpty' => $writeIfSourceIsEmpty,
            ]);
            $element = $this->objectWithPresetValue();
            $single->assignData($element, self::IMPORTED);
            static::assertSame(self::IMPORTED, $this->keyValue($element));

            $batch = $this->batchTarget([
                self::MARKER => true,
                'writeIfSourceIsEmpty' => $writeIfSourceIsEmpty,
            ]);
            $element = $this->objectWithPresetValue();
            $batch->assignData($element, [$this->batchKey() => self::IMPORTED]);
            static::assertSame(self::IMPORTED, $this->keyValue($element));
        }
    }

    public function testUnmarkedBatchEntryDropsEveryEmptyValueWhateverTheStoredSettingSays(): void
    {
        foreach ([true, false] as $writeIfSourceIsEmpty) {
            foreach (self::EMPTY_VALUES as $label => $value) {
                $batch = $this->batchTarget(['writeIfSourceIsEmpty' => $writeIfSourceIsEmpty]);
                $element = $this->objectWithPresetValue();
                $batch->assignData($element, [$this->batchKey() => $value]);

                static::assertSame(
                    self::PRESET,
                    $this->keyValue($element),
                    sprintf('stored writeIfSourceIsEmpty %s, %s', var_export($writeIfSourceIsEmpty, true), $label)
                );
            }
        }
    }

    public function testUnmarkedBatchEntryDoesNotActivateTheGroupForAnAllEmptyEntry(): void
    {
        foreach ([true, false] as $writeIfSourceIsEmpty) {
            $batch = $this->batchTarget(['writeIfSourceIsEmpty' => $writeIfSourceIsEmpty]);
            $element = $this->object();
            $batch->assignData($element, [$this->batchKey() => '']);

            static::assertArrayNotHasKey(
                $this->groupId,
                $this->classificationStore($element)->getActiveGroups(),
                'stored writeIfSourceIsEmpty ' . var_export($writeIfSourceIsEmpty, true)
            );
        }
    }

    public function testUnmarkedSingleKeyEntryWritesEveryValueWhateverTheStoredSettingSays(): void
    {
        foreach ([true, false] as $writeIfSourceIsEmpty) {
            $single = $this->singleKeyTarget(['writeIfSourceIsEmpty' => $writeIfSourceIsEmpty]);
            $element = $this->objectWithPresetValue();
            $single->assignData($element, '');

            static::assertNotSame(
                self::PRESET,
                $this->keyValue($element),
                'stored writeIfSourceIsEmpty ' . var_export($writeIfSourceIsEmpty, true)
            );
        }
    }

    public function testBothTargetsAcceptAConfigurationCarryingNoLanguage(): void
    {
        $single = new Classificationstore();
        $single->setSettings([
            'fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED,
            'keyId' => $this->batchKey(),
        ]);
        $element = $this->object();
        $single->assignData($element, self::IMPORTED);
        static::assertSame(self::IMPORTED, $this->keyValue($element));

        $batch = new ClassificationstoreBatch();
        $batch->setSettings(['fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED]);
        $element = $this->object();
        $batch->assignData($element, [$this->batchKey() => self::IMPORTED]);
        static::assertSame(self::IMPORTED, $this->keyValue($element));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function singleKeyTarget(array $settings): Classificationstore
    {
        $target = new Classificationstore();
        $target->setSettings($settings + [
            'fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED,
            'keyId' => $this->batchKey(),
        ]);

        return $target;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function batchTarget(array $settings): ClassificationstoreBatch
    {
        $target = new ClassificationstoreBatch();
        $target->setSettings($settings + ['fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED]);

        return $target;
    }

    private function batchKey(): string
    {
        return $this->groupId . '-' . $this->keyId;
    }

    private function object(): Concrete
    {
        return (new ClassificationStoreFixture())->object(self::CLASS_NAME, uniqid('cs-empty-', false));
    }

    private function objectWithPresetValue(): Concrete
    {
        $element = $this->object();
        $this->classificationStore($element)->setLocalizedKeyValue($this->groupId, $this->keyId, self::PRESET);

        return $element;
    }

    private function classificationStore(Concrete $element): ClassificationstoreModel
    {
        $getter = 'get' . ucfirst(ClassificationStoreFixture::FIELD_NON_LOCALIZED);

        return $element->$getter();
    }

    /**
     * The value as the data target assigned it, read off the store's own item map.
     *
     * Deliberately not through `getLocalizedKeyValue()`: that accessor runs the key's field
     * definition `isEmpty()` over the value before returning it
     * (`models/DataObject/Classificationstore.php:352`), and the `isEmpty()` an `input` key
     * inherits is `strlen((string) $data) < 1`
     * (`models/DataObject/ClassDefinition/Data/Extension/Text.php:30`), which cannot be handed the
     * empty-array member of the shared empty test — it raises "Array to string conversion" inside
     * core. The item map is the flattened shape `getItems()` publishes, and what the target wrote
     * into it is what these assertions are about.
     *
     * @return mixed
     */
    private function keyValue(Concrete $element)
    {
        $items = $this->classificationStore($element)->getItems();

        return $items[$this->groupId][$this->keyId]['default'] ?? null;
    }
}
