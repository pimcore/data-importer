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
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\ClassDefinition\Data\InputQuantityValue as InputQuantityValueDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\QuantityValue as QuantityValueDefinition;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\AbstractQuantityValue;
use Pimcore\Model\DataObject\Data\InputQuantityValue;
use Pimcore\Model\DataObject\Data\QuantityValue;
use Pimcore\Model\DataObject\QuantityValue\Unit as QuantityValueUnit;
use Pimcore\Tests\Support\Util\TestHelper;
use ReflectionProperty;

/**
 * Per-value dispatch on the key's own type inside one mixed `array` entry of the batch target.
 */
class ClassificationstoreBatchQuantityValueTest extends Unit
{
    private const STORE = 'diQvStore';

    private const GROUP = 'diQvGroup';

    private const KEY_QUANTITY_VALUE = 'diQvKey';

    private const KEY_INPUT_QUANTITY_VALUE = 'diIqvKey';

    private const KEY_INPUT = 'diQvInputKey';

    private const CLASS_NAME = 'DiCsQvClass';

    private const DEFINITION_UNIT = 'diQvDefinitionUnit';

    private const SOURCE_UNIT = 'diQvSourceUnit';

    /**
     * The value of an inputQuantityValue key, which holds text rather than a number.
     */
    private const TEXT_VALUE = 'abc';

    private int $groupId;

    /**
     * @var array<string, int>
     */
    private array $keyIds = [];

    protected function _before(): void
    {
        $definitionUnit = $this->unit(self::DEFINITION_UNIT);
        $this->unit(self::SOURCE_UNIT);

        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE);
        $group = $fixture->group($store->getId(), self::GROUP);

        $quantityValue = new QuantityValueDefinition();
        $quantityValue->setName(self::KEY_QUANTITY_VALUE);
        $quantityValue->setDefaultUnit($definitionUnit->getId());

        $inputQuantityValue = new InputQuantityValueDefinition();
        $inputQuantityValue->setName(self::KEY_INPUT_QUANTITY_VALUE);
        $inputQuantityValue->setDefaultUnit($definitionUnit->getId());

        $keys = [
            self::KEY_QUANTITY_VALUE => ['quantityValue', $quantityValue],
            self::KEY_INPUT_QUANTITY_VALUE => ['inputQuantityValue', $inputQuantityValue],
            self::KEY_INPUT => ['input', null],
        ];

        $sorter = 1;
        foreach ($keys as $name => [$type, $definition]) {
            $key = $fixture->key($store->getId(), $name, $type, $definition);
            $fixture->relate($group->getId(), $key->getId(), $sorter++);
            $this->keyIds[$name] = $key->getId();
        }

        $fixture->classDefinition(self::CLASS_NAME, $store->getId());
        $this->groupId = $group->getId();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testNestedPairResolvesValueAndUnitFromTheSource(): void
    {
        $sourceUnit = $this->unit(self::SOURCE_UNIT);
        $element = $this->object();

        $this->target()->assignData($element, [
            $this->batchKey(self::KEY_QUANTITY_VALUE) => [234, $sourceUnit->getId()],
        ]);

        $value = $this->keyValue($element, self::KEY_QUANTITY_VALUE);
        static::assertInstanceOf(QuantityValue::class, $value);
        static::assertSame(234.0, $value->getValue());
        static::assertSame($sourceUnit->getId(), $value->getUnitId());
    }

    public function testPlainScalarTakesTheUnitFromTheKeyDefinition(): void
    {
        $definitionUnit = $this->unit(self::DEFINITION_UNIT);
        $element = $this->object();

        $this->target()->assignData($element, [
            $this->batchKey(self::KEY_QUANTITY_VALUE) => '17.5',
        ]);

        $value = $this->keyValue($element, self::KEY_QUANTITY_VALUE);
        static::assertInstanceOf(QuantityValue::class, $value);
        static::assertSame(17.5, $value->getValue());
        static::assertSame($definitionUnit->getId(), $value->getUnitId());
    }

    public function testInputQuantityValueKeyBuildsItsOwnModelObject(): void
    {
        $sourceUnit = $this->unit(self::SOURCE_UNIT);
        $definitionUnit = $this->unit(self::DEFINITION_UNIT);
        $element = $this->object();

        $this->target()->assignData($element, [
            // an inputQuantityValue key reads unit abbreviations, as its own operator does
            $this->batchKey(self::KEY_INPUT_QUANTITY_VALUE) => [self::TEXT_VALUE, self::SOURCE_UNIT],
            $this->batchKey(self::KEY_INPUT) => 'plain',
        ]);

        $value = $this->keyValue($element, self::KEY_INPUT_QUANTITY_VALUE);
        static::assertInstanceOf(InputQuantityValue::class, $value);
        static::assertSame(self::TEXT_VALUE, $value->getValue());
        static::assertSame($sourceUnit->getId(), $value->getUnitId());

        // the plain value in the same entry is untouched by the dispatch
        static::assertSame('plain', $this->keyValue($element, self::KEY_INPUT));

        $element = $this->object();
        $this->target()->assignData($element, [
            $this->batchKey(self::KEY_INPUT_QUANTITY_VALUE) => self::TEXT_VALUE,
        ]);

        $value = $this->keyValue($element, self::KEY_INPUT_QUANTITY_VALUE);
        static::assertInstanceOf(InputQuantityValue::class, $value);
        static::assertSame($definitionUnit->getId(), $value->getUnitId());
    }

    /**
     * All four cells of (key type x unit spelling), so that neither half can pass by accident: the
     * dispatch mirrors each key type's own operator default — `id` for `quantityValue`,
     * `abbr` for `inputQuantityValue` — instead of taking one default for both.
     */
    public function testTheUnitSourceFollowsEachKeyTypesOwnOperatorDefault(): void
    {
        $sourceUnit = $this->unit(self::SOURCE_UNIT);
        $element = $this->object();

        $this->target()->assignData($element, [
            $this->batchKey(self::KEY_QUANTITY_VALUE) => [234, $sourceUnit->getId()],
            $this->batchKey(self::KEY_INPUT_QUANTITY_VALUE) => [self::TEXT_VALUE, self::SOURCE_UNIT],
        ]);

        static::assertSame(
            $sourceUnit->getId(),
            $this->quantityValue($element, self::KEY_QUANTITY_VALUE)->getUnitId(),
            'a quantityValue key given a unit id still resolves to that unit'
        );
        static::assertSame(
            $sourceUnit->getId(),
            $this->quantityValue($element, self::KEY_INPUT_QUANTITY_VALUE)->getUnitId(),
            'an inputQuantityValue key given a unit abbreviation resolves to that unit'
        );

        // the spellings swapped: each type reads only its own, so neither resolves
        $element = $this->object();
        $this->target()->assignData($element, [
            $this->batchKey(self::KEY_QUANTITY_VALUE) => [234, self::SOURCE_UNIT],
            $this->batchKey(self::KEY_INPUT_QUANTITY_VALUE) => [self::TEXT_VALUE, $sourceUnit->getId()],
        ]);

        static::assertNull($this->quantityValue($element, self::KEY_QUANTITY_VALUE)->getUnitId());
        static::assertNull($this->quantityValue($element, self::KEY_INPUT_QUANTITY_VALUE)->getUnitId());
    }

    /**
     * A pair carrying neither a quantity nor a resolvable unit writes no value, rather than a
     * quantity of zero. Asserted over a key that already holds a value, so that the blank pair is
     * shown to clear it: without the guard the `(float)` cast turns `['', null]` into `0.0` and the
     * key reads back as a real zero nobody imported.
     */
    public function testABlankPairWritesNoValueRatherThanZero(): void
    {
        $presets = [
            self::KEY_QUANTITY_VALUE => '17.5',
            self::KEY_INPUT_QUANTITY_VALUE => self::TEXT_VALUE,
        ];

        foreach ([['', null], [null, null]] as $pair) {
            foreach ($presets as $keyName => $preset) {
                $label = $keyName . ', [' . var_export($pair[0], true) . ', ' . var_export($pair[1], true) . ']';

                $element = $this->object();
                $target = $this->target();

                $target->assignData($element, [$this->batchKey($keyName) => $preset]);
                static::assertInstanceOf(
                    AbstractQuantityValue::class,
                    $this->keyValue($element, $keyName),
                    'the key holds a value before the blank pair, ' . $label
                );

                $target->assignData($element, [$this->batchKey($keyName) => $pair]);
                static::assertNull($this->keyValue($element, $keyName), $label);
            }
        }
    }

    /**
     * The assertions above read the in-memory store, where a loss inside the marshaller or in the
     * two value columns cannot show. Both quantity key types are therefore read back off a forced
     * reload after a real save. The source unit differs from the key definition's default unit, so
     * a unit dropped on the way to the database cannot be masked by the default being restored on
     * the way back.
     */
    public function testBothQuantityKeyTypesKeepTheirValueAndUnitAcrossASave(): void
    {
        $sourceUnit = $this->unit(self::SOURCE_UNIT);
        static::assertNotSame(
            $this->unit(self::DEFINITION_UNIT)->getId(),
            $sourceUnit->getId(),
            'the source unit has to differ from the definition default for this test to prove anything'
        );

        $element = $this->object();
        $this->target()->assignData($element, [
            $this->batchKey(self::KEY_QUANTITY_VALUE) => [234, $sourceUnit->getId()],
            $this->batchKey(self::KEY_INPUT_QUANTITY_VALUE) => [self::TEXT_VALUE, self::SOURCE_UNIT],
        ]);
        $element->save();

        $reloaded = Concrete::getById((int) $element->getId(), ['force' => true]);
        static::assertInstanceOf(Concrete::class, $reloaded);

        $quantityValue = $this->keyValue($reloaded, self::KEY_QUANTITY_VALUE);
        static::assertInstanceOf(QuantityValue::class, $quantityValue);
        // the value column is text, so a reloaded quantity arrives as a numeric string
        static::assertSame(234.0, (float) $quantityValue->getValue(), 'the stored quantity');
        static::assertSame($sourceUnit->getId(), $quantityValue->getUnitId(), 'the stored unit id');

        $inputQuantityValue = $this->keyValue($reloaded, self::KEY_INPUT_QUANTITY_VALUE);
        static::assertInstanceOf(InputQuantityValue::class, $inputQuantityValue);
        static::assertSame(self::TEXT_VALUE, $inputQuantityValue->getValue(), 'the stored input quantity');
        static::assertSame($sourceUnit->getId(), $inputQuantityValue->getUnitId(), 'the stored unit id');
    }

    public function testUnknownUnitIdInTheSourceResolvesToNoUnit(): void
    {
        $element = $this->object();

        $this->target()->assignData($element, [
            $this->batchKey(self::KEY_QUANTITY_VALUE) => [234, 'di-no-such-unit'],
        ]);

        $value = $this->keyValue($element, self::KEY_QUANTITY_VALUE);
        static::assertInstanceOf(QuantityValue::class, $value);
        static::assertNull($value->getUnitId());
    }

    public function testQuantityValueObjectsPassThroughUnchanged(): void
    {
        $sourceUnit = $this->unit(self::SOURCE_UNIT);
        $element = $this->object();

        // the shape QuantityValueArray::process() produces
        $original = new QuantityValue(42.0, $sourceUnit->getId());
        $this->target()->assignData($element, [
            $this->batchKey(self::KEY_QUANTITY_VALUE) => $original,
        ]);

        static::assertSame($original, $this->keyValue($element, self::KEY_QUANTITY_VALUE));
    }

    /**
     * The unit source is decided by the key's own type, so no unit setting can reach the dispatch
     * through the configuration. Asserted on the trait's own properties rather than on a resolved
     * unit: with the source fixed per key type, a honoured `unitSourceSelect` would leave no trace
     * in a written value, and an assertion on one could not fail.
     */
    public function testSettingsAcceptNoUnitKeys(): void
    {
        $target = new ClassificationstoreBatch();
        $target->setSettings([
            'fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED,
            'unitSourceSelect' => 'static',
            'staticUnitSelect' => '1',
            'unitNullIfNoValueCheckbox' => true,
        ]);

        $untouched = ['unitSource' => null, 'staticUnitId' => null, 'unitNullIfNoValue' => false];
        foreach ($untouched as $property => $expected) {
            static::assertSame(
                $expected,
                (new ReflectionProperty(ClassificationstoreBatch::class, $property))->getValue($target),
                $property . ' was taken from the mapping entry settings'
            );
        }
    }

    private function target(): ClassificationstoreBatch
    {
        $target = new ClassificationstoreBatch();
        $target->setSettings(['fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED]);

        return $target;
    }

    private function unit(string $abbreviation): QuantityValueUnit
    {
        $unit = QuantityValueUnit::getByAbbreviation($abbreviation);
        if ($unit instanceof QuantityValueUnit) {
            return $unit;
        }

        $unit = new QuantityValueUnit();
        $unit->setAbbreviation($abbreviation);
        $unit->save();

        return $unit;
    }

    private function batchKey(string $keyName): string
    {
        return $this->groupId . '-' . $this->keyIds[$keyName];
    }

    private function object(): Concrete
    {
        return (new ClassificationStoreFixture())->object(self::CLASS_NAME, uniqid('cs-qv-', false));
    }

    private function quantityValue(Concrete $element, string $keyName): AbstractQuantityValue
    {
        $value = $this->keyValue($element, $keyName);
        static::assertInstanceOf(AbstractQuantityValue::class, $value);

        return $value;
    }

    /**
     * @return mixed
     */
    private function keyValue(Concrete $element, string $keyName)
    {
        $getter = 'get' . ucfirst(ClassificationStoreFixture::FIELD_NON_LOCALIZED);
        /** @var ClassificationstoreModel $classificationStore */
        $classificationStore = $element->$getter();

        return $classificationStore->getLocalizedKeyValue($this->groupId, $this->keyIds[$keyName]);
    }
}
