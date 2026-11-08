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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\StoreGroupCheck;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\ValueError;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;

class AdHocStoreGroupCheckTest extends Unit
{
    private const int GROUP = 7;

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

    private function object(
        Classificationstore $store,
        bool $localized = false,
        bool $allowInherit = false
    ): Concrete {
        $definition = new Data\Classificationstore();
        $definition->setLocalized($localized);
        $class = new ClassDefinition();
        $class->setFieldDefinitions(['attributes' => $definition]);
        $class->setAllowInherit($allowInherit);

        $object = $this->createMock(Concrete::class);
        $object->method('get')->with('attributes')->willReturn($store);
        $object->method('getClass')->willReturn($class);

        return $object;
    }
}
