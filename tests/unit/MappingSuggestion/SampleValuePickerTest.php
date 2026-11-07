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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\MappingSuggestion;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\SampleValuePicker;
use Random\Engine\Mt19937;
use Random\Randomizer;

class SampleValuePickerTest extends Unit
{
    private function picker(int $seed = 1): SampleValuePicker
    {
        return new SampleValuePicker(new Randomizer(new Mt19937($seed)));
    }

    public function testItDrawsOnlyForTheColumnsAsked(): void
    {
        $samples = $this->picker()->pick(
            [['sku' => 'A-1', 'color' => 'red'], ['sku' => 'A-2', 'color' => 'blue']],
            ['color']
        );

        $this->assertSame(['color'], array_keys($samples));
        $values = $samples['color'];
        sort($values);
        $this->assertSame(['blue', 'red'], $values);
    }

    public function testItKeepsAtMostThreeValuesPerColumn(): void
    {
        $rows = [];
        for ($number = 0; $number < 500; $number++) {
            $rows[] = ['sku' => 'A-' . $number];
        }

        $this->assertCount(3, $this->picker()->pick($rows, ['sku'])['sku']);
    }

    /**
     * The rule of the sample values: no record. Over many rows the values of two columns must not come from the same
     * rows, and every part of the file must have its chance.
     */
    public function testEachColumnIsDrawnOnItsOwnSoTheValuesFormNoRecord(): void
    {
        $rows = [];
        for ($number = 0; $number < 1000; $number++) {
            $rows[] = ['a' => 'a' . $number, 'b' => 'b' . $number];
        }

        $samples = $this->picker()->pick($rows, ['a', 'b']);

        $rowsOf = static fn (array $values): array => array_map(
            static fn (string $value): int => (int) substr($value, 1),
            $values
        );
        $this->assertNotEquals($rowsOf($samples['a']), $rowsOf($samples['b']));
        $this->assertSame([], array_intersect($rowsOf($samples['a']), $rowsOf($samples['b'])));
        $this->assertGreaterThan(2, max($rowsOf($samples['a'])), 'only the first rows were drawn');
    }

    public function testASmallFileIsNotHandedOverInRowOrderInEachColumn(): void
    {
        $rows = [
            ['a' => 'a0', 'b' => 'b0'],
            ['a' => 'a1', 'b' => 'b1'],
            ['a' => 'a2', 'b' => 'b2'],
        ];

        $aligned = 0;
        for ($seed = 1; $seed <= 20; $seed++) {
            $samples = $this->picker($seed)->pick($rows, ['a', 'b']);
            $aligned += array_map(static fn (string $v): string => substr($v, 1), $samples['a'])
                === array_map(static fn (string $v): string => substr($v, 1), $samples['b']) ? 1 : 0;
        }

        $this->assertLessThan(20, $aligned, 'the two columns always came in the same row order');
    }

    public function testOneRowGivesNothingBecauseItsValuesWouldBeARecord(): void
    {
        $this->assertSame([], $this->picker()->pick([['sku' => 'A-1', 'color' => 'red']], ['sku', 'color']));
        $this->assertSame([], $this->picker()->pick([], ['sku']));
    }

    /**
     * @dataProvider oneRecordAndBlankRows
     */
    public function testBlankRowsDoNotMakeOneRecordCountAsSeveralRows(array $rows, array $columns): void
    {
        $this->assertSame([], $this->picker()->pick($rows, $columns));
    }

    public function oneRecordAndBlankRows(): array
    {
        return [
            'a row of nulls' => [
                [['sku' => 'A-1', 'color' => 'red'], ['sku' => null, 'color' => null]],
                ['sku', 'color'],
            ],
            'a blank CSV line' => [[['A-1', 'red'], [null]], ['0', '1']],
            'a value only in a column not asked for' => [
                [['sku' => 'A-1', 'color' => 'red'], ['sku' => 'A-2', 'color' => '']],
                ['color'],
            ],
        ];
    }

    public function testTwoRowsWithValuesInDifferentColumnsStillGiveSamples(): void
    {
        $samples = $this->picker()->pick(
            [['sku' => 'A-1', 'color' => null], ['sku' => null, 'color' => 'red']],
            ['sku', 'color']
        );

        $this->assertSame(['sku' => ['A-1'], 'color' => ['red']], $samples);
    }

    public function testColumnsWhoseOnlyValuesShareOneRowAreLeftOutBecauseTogetherTheyAreThatRecord(): void
    {
        $samples = $this->picker()->pick(
            [['sku' => 'A-1', 'color' => 'red', 'name' => 'Secret X'], ['sku' => 'A-2']],
            ['sku', 'color', 'name']
        );

        $this->assertSame(['sku'], array_keys($samples));
        $values = $samples['sku'];
        sort($values);
        $this->assertSame(['A-1', 'A-2'], $values);
    }

    public function testAColumnWithOneValueStaysWhenNoOtherColumnHasItsOnlyValueInThatRow(): void
    {
        $samples = $this->picker()->pick(
            [['sku' => 'A-1', 'color' => 'red'], ['sku' => 'A-2', 'name' => 'Lamp']],
            ['sku', 'color', 'name']
        );

        $this->assertSame(['red'], $samples['color']);
        $this->assertSame(['Lamp'], $samples['name']);
        $this->assertCount(2, $samples['sku']);
    }

    public function testAColumnThatSendsOneValueIsLeftOutWithTheOtherOneValueColumnOfItsRow(): void
    {
        // the customer is in both rows, but sends one value: with the only e-mail it would be the first row's record
        $samples = $this->picker()->pick(
            [['customer' => 'C-1', 'email' => 'person@example.test'], ['customer' => 'C-1']],
            ['customer', 'email']
        );

        $this->assertSame([], $samples);
    }

    public function testOneValueColumnsSharingRowsAreLeftOutWhileAColumnWithSeveralValuesStays(): void
    {
        $rows = [];
        for ($number = 0; $number < 20; $number++) {
            $rows[] = ['currency' => 'EUR', 'country' => 'DE', 'sku' => 'A-' . $number];
        }

        $samples = $this->picker()->pick($rows, ['currency', 'country', 'sku']);

        $this->assertSame(['sku'], array_keys($samples));
        $this->assertCount(3, $samples['sku']);
    }

    public function testAOneValueColumnStaysBesideColumnsWithSeveralValues(): void
    {
        $samples = $this->picker()->pick(
            [['currency' => 'EUR', 'sku' => 'A-1'], ['currency' => 'EUR', 'sku' => 'A-2']],
            ['currency', 'sku']
        );

        $this->assertSame(['EUR'], $samples['currency']);
        $this->assertCount(2, $samples['sku']);
    }

    public function testALongTextIsCutToFiftyCharactersNotBytes(): void
    {
        $samples = $this->picker()->pick(
            [['text' => str_repeat('ä', 80)], ['text' => str_repeat('ä', 80)]],
            ['text']
        );

        $this->assertSame([str_repeat('ä', 50)], $samples['text']);
    }

    public function testEmptyCellsAndValuesThatAreNoTextAreLeftOutAndAColumnWithoutValuesHasNoEntry(): void
    {
        $samples = $this->picker()->pick(
            [
                ['a' => '', 'b' => null, 'c' => 12, 'd' => '  ', 'e' => ['x']],
                ['a' => 'x', 'b' => null, 'c' => 1.5, 'd' => true],
            ],
            ['a', 'b', 'c', 'd', 'e']
        );

        $this->assertSame(['x'], $samples['a']);
        $values = $samples['c'];
        sort($values);
        $this->assertSame(['1.5', '12'], $values);
        $this->assertArrayNotHasKey('b', $samples);
        $this->assertArrayNotHasKey('d', $samples);
        $this->assertArrayNotHasKey('e', $samples);
    }

    public function testAValueThatRepeatsIsSentOnce(): void
    {
        $samples = $this->picker()->pick(array_fill(0, 5, ['state' => 'active']), ['state']);

        $this->assertSame(['active'], $samples['state']);
    }

    public function testANumericColumnIndexFindsItsCell(): void
    {
        $samples = $this->picker()->pick([['first', 'second'], ['first', 'second']], ['1']);

        $this->assertSame(['second'], $samples['1']);
    }

    public function testItStopsReadingAfterTenThousandRows(): void
    {
        $read = 0;
        $rows = (static function () use (&$read): iterable {
            while (true) {
                $read++;

                yield ['sku' => 'A'];
            }
        })();

        $this->picker()->pick($rows, ['sku']);

        $this->assertSame(10001, $read);
    }
}
