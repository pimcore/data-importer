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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\FieldWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ImportField;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\StoreGroupCheck;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ValueError;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;

class AdHocStoreGroupCheckTest extends Unit
{
    private const int GROUP = 7;

    private const int OTHER_GROUP = 8;

    private const int WEIGHT = 11;

    private const int HEIGHT = 12;

    public function testAMandatoryKeyTheFileDoesNotNameIsRefusedOnceItsGroupIsActive(): void
    {
        $store = $this->store([self::GROUP => [self::WEIGHT => ['default' => '5 kg']]]);

        $refused = $this->check([$this->key(self::WEIGHT), $this->key(self::HEIGHT, 'height')])
            ->refused($this->object($store));

        $this->assertCount(1, $refused);
        $this->assertSame('attributes', $refused[0]['field']);
        $this->assertSame(self::HEIGHT, $refused[0]['key']->keyId);
        $this->assertSame('Basics › height', $refused[0]['label']);
        $this->assertSame('', $refused[0]['value']);
        $this->assertSame(ValueError::Required, $refused[0]['error']);
        $this->assertSame(['default'], $refused[0]['languages']);
    }

    public function testAKeyThatIsNotMandatoryMayStayEmpty(): void
    {
        $key = $this->key(self::HEIGHT, 'height', mandatory: false);

        $this->assertSame([], $this->check([$key])->refused($this->object($this->store([]))));
    }

    public function testAGroupThatIsNotActiveIsNotLookedAt(): void
    {
        $store = $this->createMock(Classificationstore::class);
        $store->method('getActiveGroups')->willReturn([self::GROUP => false]);
        $asked = 0;
        $check = new StoreGroupCheck(['attributes'], ['en'], static function () use (&$asked): array {
            ++$asked;

            return [];
        });

        $this->assertSame([], $check->refused($this->object($store)));
        $this->assertSame(0, $asked);
    }

    public function testAKeyEmptyInALocalizedStoreIsRefusedPerLanguageWithoutADefaultOrParentToFallBackOn(): void
    {
        $store = $this->store([self::GROUP => [self::WEIGHT => ['en' => '5 kg']]]);

        $refused = $this->check([$this->key(self::WEIGHT)])
            ->refused($this->object($store, localized: true));

        $this->assertCount(1, $refused);
        $this->assertSame(['default', 'de'], $refused[0]['languages']);
        $this->assertSame('Basics › weight (default, de)', $refused[0]['label']);
    }

    public function testAnInheritingClassTakesTheValueAReadReturnsInEachLanguage(): void
    {
        $store = $this->createMock(Classificationstore::class);
        $store->method('getActiveGroups')->willReturn([self::GROUP => true]);
        $store->method('getLocalizedKeyValue')->willReturnCallback(
            function (int $group, int $key, string $language, bool $ignoreFallback): ?string {
                $this->assertTrue($ignoreFallback, 'the save reads without the fallback language');
                $this->assertTrue(DataObject::doGetInheritedValues(), 'and with the parents');

                return $language === 'de' ? null : '1 kg';
            }
        );

        $refused = $this->check([$this->key(self::WEIGHT)])
            ->refused($this->object($store, localized: true, allowInherit: true));

        $this->assertCount(1, $refused);
        $this->assertSame(['de'], $refused[0]['languages']);
    }

    public function testAValueItsKeyRefusesIsNamedWithItsValue(): void
    {
        $definition = new Input();
        $definition->setRegex('^\d+$');
        $store = $this->store([self::GROUP => [self::WEIGHT => ['default' => 'heavy']]]);

        $refused = $this->check([$this->key(self::WEIGHT, definition: $definition)])
            ->refused($this->object($store));

        $this->assertCount(1, $refused);
        $this->assertSame(ValueError::Invalid, $refused[0]['error']);
        $this->assertSame('heavy', $refused[0]['value']);
    }

    public function testAStoreHoldingMoreGroupsThanItAllowsIsRefusedAsAWhole(): void
    {
        $store = $this->createMock(Classificationstore::class);
        $store->method('getActiveGroups')->willReturn([self::GROUP => true, self::OTHER_GROUP => true]);
        $store->method('getItems')->willReturn([]);

        $refused = $this->check([$this->key(self::WEIGHT, mandatory: false)])
            ->refused($this->object($store, maxItems: 1));

        $this->assertCount(1, $refused);
        $this->assertSame('attributes', $refused[0]['field']);
        $this->assertNull($refused[0]['key']);
        $this->assertSame('Attributes', $refused[0]['label']);
        $this->assertSame(ValueError::TooManyGroups, $refused[0]['error']);
        $this->assertSame([], $refused[0]['languages']);
    }

    public function testAStoreWithinItsLimitOrWithoutOneIsNotRefused(): void
    {
        $key = $this->key(self::WEIGHT, mandatory: false);
        $store = $this->store([]);

        $this->assertSame([], $this->check([$key])->refused($this->object($store, maxItems: 1)));
        $this->assertSame([], $this->check([$key])->refused($this->object($store)));
    }

    public function testAGroupsKeysAreReadOncePerRun(): void
    {
        $asked = 0;
        $check = new StoreGroupCheck(['attributes'], [], function () use (&$asked): array {
            ++$asked;

            return [$this->key(self::WEIGHT)];
        });

        $check->refused($this->object($this->store([])));
        $check->refused($this->object($this->store([])));

        $this->assertSame(1, $asked, 'once for the whole file, not once per row');
    }

    public function testAFieldThatIsNoClassificationStoreIsPassedOver(): void
    {
        $object = $this->createMock(Concrete::class);
        $object->method('get')->willReturn('text');
        $object->method('getClass')->willReturn(new ClassDefinition());

        $this->assertSame([], $this->check([$this->key(self::WEIGHT)])->refused($object));
    }

    public function testAnOptionalKeyEmptyBeforeIsNotRefusedOnceItsUnusableValueIsLeftOut(): void
    {
        $definition = new Input();
        $definition->setRegex('^\d+$');
        $keys = [$this->key(self::WEIGHT, definition: $definition)];
        $object = $this->objectHolding($this->realStore([], [self::GROUP => true]));
        $writer = $this->writer(self::GROUP, self::WEIGHT, 'heavy');
        $check = $this->check($keys);
        $before = $check->stores($object);
        $writer->write($object, 0, []);

        $this->assertCount(1, $check->refused($object), 'written, the value is refused');

        $outcome = $check->refusedWithout($object, $before, [$writer], [0 => true], 0, []);
        $this->assertSame(['lifted' => [], 'remaining' => [], 'leftOut' => [0 => true]], $outcome);
    }

    public function testAGroupOnlyAnUnusableValueActivatesCountsForNothingOnceThatValueIsLeftOut(): void
    {
        $object = $this->objectHolding($this->realStore([], [self::GROUP => true]), maxItems: 1);
        $check = $this->check([$this->key(self::WEIGHT, mandatory: false)]);
        $writer = $this->writer(self::OTHER_GROUP, self::HEIGHT, 'wide');
        $before = $check->stores($object);
        $writer->write($object, 0, []);

        $this->assertSame(ValueError::TooManyGroups, $check->refused($object)[0]['error'], 'written, two groups');

        $outcome = $check->refusedWithout($object, $before, [$writer], [0 => true], 0, []);
        $this->assertSame(['lifted' => [], 'remaining' => [], 'leftOut' => [0 => true]], $outcome);
    }

    public function testARefusalOfAValueTheColumnsKeptStaysWhenAnotherValueIsLeftOut(): void
    {
        $object = $this->objectHolding($this->realStore([], [self::GROUP => true]), maxItems: 1);
        $check = $this->check([$this->key(self::WEIGHT, mandatory: false)]);
        $left = $this->writer(self::GROUP, self::WEIGHT, 'bad');
        $kept = $this->writer(self::OTHER_GROUP, self::HEIGHT, 'wide');
        $before = $check->stores($object);
        $left->write($object, 0, []);
        $kept->write($object, 0, []);

        $outcome = $check->refusedWithout($object, $before, [$left, $kept], [0 => true], 0, []);

        $this->assertSame([], $outcome['lifted']);
        $this->assertCount(1, $outcome['remaining'], 'the other group is still activated by the kept value');
        $this->assertSame(ValueError::TooManyGroups, $outcome['remaining'][0]['error']);
        $this->assertNull($object->get('attributes')->getItems()[self::GROUP][self::WEIGHT]['default'] ?? null);
    }

    public function testAKeyTheFileEmptiesIsLiftedWhenTheOldValueComesBack(): void
    {
        $object = $this->objectHolding($this->realStore(
            [self::GROUP => [self::WEIGHT => ['default' => '1 kg']]],
            [self::GROUP => true]
        ));
        $check = $this->check([$this->key(self::WEIGHT)]);
        $writer = $this->writer(self::GROUP, self::WEIGHT, null);
        $before = $check->stores($object);
        $writer->write($object, 0, []);

        $outcome = $check->refusedWithout($object, $before, [$writer], [], 0, []);

        $this->assertSame([], $outcome['remaining']);
        $this->assertCount(1, $outcome['lifted']);
        $this->assertSame(ValueError::Required, $outcome['lifted'][0]['error']);
        $this->assertSame([0 => true], $outcome['leftOut'], 'the run must go without the writer the preview left out');
    }

    public function testAKeyEmptyBeforeInAnActiveGroupRemainsRefusedAndIsNotCountedAsLifted(): void
    {
        $object = $this->objectHolding($this->realStore([], [self::GROUP => true]));
        $check = $this->check([$this->key(self::WEIGHT)]);
        $writer = $this->writer(self::GROUP, self::WEIGHT, null);
        $before = $check->stores($object);
        $writer->write($object, 0, []);

        $outcome = $check->refusedWithout($object, $before, [$writer], [], 0, []);

        $this->assertSame([], $outcome['lifted'], 'it is refused either way');
        $this->assertCount(1, $outcome['remaining']);
        $this->assertSame([0 => true], $check->writersOf([$writer], $outcome['remaining'][0]));
    }

    public function testARefusalOfTheWholeStoreHasNoWriter(): void
    {
        $writer = $this->writer(self::GROUP, self::WEIGHT, 'x');

        $this->assertSame([], $this->check([])->writersOf([$writer], [
            'field' => 'attributes',
            'key' => null,
            'label' => 'Attributes',
            'value' => '',
            'error' => ValueError::TooManyGroups,
            'languages' => [],
        ]));
    }

    /**
     * @param list<array{key: StoreKey, label: string, definition: Data}> $keys
     */
    private function check(array $keys): StoreGroupCheck
    {
        return new StoreGroupCheck(['attributes'], ['de', 'en'], static fn (): array => $keys);
    }

    /**
     * @return array{key: StoreKey, label: string, definition: Data}
     */
    private function key(
        int $keyId,
        string $name = 'weight',
        bool $mandatory = true,
        ?Data $definition = null
    ): array {
        if ($definition === null) {
            $definition = new Input();
            $definition->setMandatory($mandatory);
        }

        return [
            'key' => new StoreKey(self::GROUP, $keyId),
            'label' => 'Basics › ' . $name,
            'definition' => $definition,
        ];
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $items
     */
    private function store(array $items): Classificationstore
    {
        $store = $this->createMock(Classificationstore::class);
        $store->method('getActiveGroups')->willReturn([self::GROUP => true]);
        $store->method('getItems')->willReturn($items);

        return $store;
    }

    /**
     * A store that keeps what is written into it, without the key definitions a real write loads.
     *
     * @param array<int, array<int, array<string, mixed>>> $items
     * @param array<int, bool> $activeGroups
     */
    private function realStore(array $items, array $activeGroups): Classificationstore
    {
        $store = new Classificationstore();
        $store->setItems($items);
        $store->setActiveGroups($activeGroups);

        return $store;
    }

    private function objectHolding(Classificationstore $store, ?int $maxItems = null): Concrete
    {
        $definition = new Data\Classificationstore();
        $definition->setMaxItems($maxItems);
        $definition->setTitle('Attributes');
        $class = new ClassDefinition();
        $class->setFieldDefinitions(['attributes' => $definition]);

        $object = $this->createMock(Concrete::class);
        $object->method('getClass')->willReturn($class);
        $object->method('get')->willReturnCallback(static function () use (&$store): Classificationstore {
            return $store;
        });
        $object->method('set')->willReturnCallback(
            static function (string $field, Classificationstore $value) use (&$store, $object): Concrete {
                $store = $value;

                return $object;
            }
        );

        return $object;
    }

    private function writer(int $group, int $key, ?string $value): FieldWriter
    {
        return new class($group, $key, $value) implements FieldWriter {
            public function __construct(private int $group, private int $key, private ?string $value)
            {
            }

            public function field(): ImportField
            {
                return new ImportField('attributes', null, 'column', new StoreKey($this->group, $this->key));
            }

            public function write(Concrete $object, int $index, array $row): ?ValueError
            {
                $store = $object->get('attributes');
                $items = $store->getItems();
                unset($items[$this->group][$this->key]);
                if ($this->value !== null) {
                    $items[$this->group][$this->key]['default'] = $this->value;
                }
                $store->setItems($items);
                $store->setActiveGroups($store->getActiveGroups() + [$this->group => true]);

                return null;
            }

            public function fileValue(array $row): ?string
            {
                return $this->value;
            }

            public function forUser(User $user): self
            {
                return $this;
            }
        };
    }

    private function object(
        Classificationstore $store,
        bool $localized = false,
        bool $allowInherit = false,
        ?int $maxItems = null
    ): Concrete {
        $definition = new Data\Classificationstore();
        $definition->setLocalized($localized);
        $definition->setMaxItems($maxItems);
        $definition->setTitle('Attributes');
        $class = new ClassDefinition();
        $class->setFieldDefinitions(['attributes' => $definition]);
        $class->setAllowInherit($allowInherit);

        $object = $this->createMock(Concrete::class);
        $object->method('get')->with('attributes')->willReturn($store);
        $object->method('getClass')->willReturn($class);

        return $object;
    }
}
