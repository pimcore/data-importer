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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationAllowList;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService as Types;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationCell;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\User;

class AdHocTransformationChainTest extends Unit
{
    private const string ARTICLE = '/Products/Chair';

    private const string HIDDEN_ARTICLE = '/Secret/Prototype';

    private const string IMAGE = '/images/1.jpg';

    private const string HIDDEN_IMAGE = '/Secret/prototype.jpg';

    private const string DAY = '2026-11-07';

    private const string LAST_DAY_OF_FEBRUARY = '2026-02-28';

    private const array TEXT_STEPS = ['trim', 'stringReplace', 'conditionalConversion'];

    public function testTheStepsRunOnTheColumnsValuesOneAfterTheOther(): void
    {
        $result = $this->chainOn([' chair ', ' table', null], [
            $this->step(TransformationAllowList::TRIM),
            $this->step(TransformationAllowList::REPLACE, ['search' => 'a', 'replace' => 'A']),
        ], [Types::DEFAULT_TYPE]);

        $this->assertSame([[' chair '], [' table'], []], $this->items($result->getValues()));
        $this->assertSame(['trim', 'stringReplace'], array_column($result->getSteps(), 'type'));
        $this->assertSame([['chair'], ['table'], []], $this->items($result->getSteps()[0]['values']));
        $this->assertSame([['chAir'], ['tAble'], []], $this->items($result->getSteps()[1]['values']));
        $this->assertSame(['default', 'default'], array_column($result->getSteps(), 'resultType'));
        $this->assertNull($result->getConversion());
        $this->assertSame(Types::DEFAULT_TYPE, $result->getResultType());
        $this->assertTrue($result->getFits());
        $this->assertSame([['chAir'], ['tAble'], []], $this->items($result->getResults()));
    }

    public function testMappedValuesTakeTheirCounterpartAndOthersStay(): void
    {
        $result = $this->chainOn(['M', 'L', 'XL'], [
            $this->step(TransformationAllowList::MAP_VALUES, ['original' => 'M|L', 'converted' => 'Medium|Large']),
        ], [Types::DEFAULT_TYPE]);

        $this->assertSame([['Medium'], ['Large'], ['XL']], $this->items($result->getResults()));
    }

    public function testAnyOtherValueTakesTheCounterpartOfTheStar(): void
    {
        $result = $this->chainOn(['M', 'XL', ''], [
            $this->step(TransformationAllowList::MAP_VALUES, ['original' => 'M|*', 'converted' => 'Medium|Other']),
        ], [Types::DEFAULT_TYPE]);

        $this->assertSame([['Medium'], ['Other'], ['Other']], $this->items($result->getResults()));
    }

    public function testAMultiselectTakesNoLoadedObjectOrAsset(): void
    {
        $result = $this->chainOn(['1'], [], [Types::DEFAULT_ARRAY]);

        $this->assertSame('asArray', $result->getConversion());
        $this->assertSame([...self::TEXT_STEPS, 'explode'], $result->getOffered());

        foreach ([TransformationAllowList::LOAD_OBJECT, TransformationAllowList::LOAD_ASSET] as $load) {
            $loaded = $this->chainOn(['1'], [$this->step($load)], [Types::DEFAULT_ARRAY]);

            $this->assertFalse($loaded->getFits(), $load);
            $this->assertNull($loaded->getConversion(), $load);
            $this->assertSame([], $loaded->getResults(), $load);
        }
    }

    public function testAListSplitFromTheColumnGoesIntoAMultiselectAsItIs(): void
    {
        $result = $this->chainOn(['red,blue', ''], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => ',']),
        ], [Types::DEFAULT_ARRAY]);

        $this->assertNull($result->getConversion());
        $this->assertSame(Types::DEFAULT_ARRAY, $result->getResultType());
        $this->assertSame([['red', 'blue'], []], $this->items($result->getResults()));
    }

    /**
     * @return iterable<string, array{list<string>, string, string, list<string|null>, list<list<string>>}>
     */
    public static function conversionsByFieldType(): iterable
    {
        yield 'a number for a numeric field, which takes a boolean too' => [
            [Types::NUMERIC, Types::BOOLEAN],
            'numeric',
            Types::NUMERIC,
            ['12.50', '3'],
            [['12.5'], ['3']],
        ];
        yield 'a date for a date field' => [[Types::DATE], 'date', Types::DATE, [self::DAY], [[self::DAY]]];
        yield 'an empty cell for a numeric field, which stays empty' => [
            [Types::NUMERIC],
            'numeric',
            Types::NUMERIC,
            ['', null, '3'],
            [[], [], ['3']],
        ];
        yield 'a one item list for a multiselect' => [
            [Types::DEFAULT_ARRAY],
            'asArray',
            Types::DEFAULT_ARRAY,
            ['red'],
            [['red']],
        ];
        yield 'a boolean for a checkbox' => [
            [Types::BOOLEAN],
            'boolean',
            Types::BOOLEAN,
            ['yes', 'no'],
            [['true'], ['false']],
        ];
    }

    /**
     * @dataProvider conversionsByFieldType
     *
     * @param list<string> $accepted
     * @param list<string|null> $values
     * @param list<list<string>> $results
     */
    public function testTheColumnIsConvertedToTheFieldsType(
        array $accepted,
        string $conversion,
        string $type,
        array $values,
        array $results
    ): void {
        $result = $this->chainOn($values, [], $accepted);

        $this->assertSame($conversion, $result->getConversion());
        $this->assertSame($type, $result->getResultType());
        $this->assertTrue($result->getFits());
        $this->assertSame($results, $this->items($result->getResults()));
    }

    public function testADayTheMonthDoesNotHaveIsNotConvertedToAnotherDate(): void
    {
        $result = $this->chainOn(['2026-02-30', self::LAST_DAY_OF_FEBRUARY], [], [Types::DATE]);

        $this->assertSame([true, false], $this->failed($result->getResults()));
        $this->assertSame([[], [self::LAST_DAY_OF_FEBRUARY]], $this->items($result->getResults()));

        $listed = $this->chainOn(['2026-02-28,2026-02-30', self::LAST_DAY_OF_FEBRUARY], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => ',']),
        ], [Types::DATE_ARRAY]);

        $this->assertSame('date', $listed->getConversion());
        $this->assertSame([true, false], $this->failed($listed->getResults()));
        $this->assertSame([[], [self::LAST_DAY_OF_FEBRUARY]], $this->items($listed->getResults()));
    }

    public function testAValueTheConversionCannotReadFailsInItsRowOnly(): void
    {
        $result = $this->chainOn([self::DAY, '07.11.2026', ''], [], [Types::DATE]);

        $this->assertSame([false, true, false], $this->failed($result->getResults()));
        $this->assertSame([[self::DAY], [], []], $this->items($result->getResults()));
    }

    /**
     * @return iterable<string, array{list<string>, list<string|null>, list<bool>, list<list<string>>}>
     */
    public static function valuesAConversionCannotRead(): iterable
    {
        // the conversion reads a decimal point only; a Replace step turns the comma into one
        yield 'a decimal comma for a numeric field' => [
            [Types::NUMERIC],
            ['12,50', '12.50'],
            [true, false],
            [[], ['12.5']],
        ];
        yield 'a text for a numeric field' => [[Types::NUMERIC], ['n/a', ''], [true, false], [[], []]];
        yield 'a yes or no in another language for a checkbox' => [
            [Types::BOOLEAN],
            ['Ja', 'X', 'yes', 'off', '', null, '0'],
            [true, true, false, false, false, false, false],
            [[], [], ['true'], ['false'], [], [], ['false']],
        ];
    }

    /**
     * @dataProvider valuesAConversionCannotRead
     *
     * @param list<string> $accepted
     * @param list<string|null> $values
     * @param list<bool> $failed
     * @param list<list<string>> $results
     */
    public function testANumberOrYesNoTheConversionCannotReadFailsInItsRow(
        array $accepted,
        array $values,
        array $failed,
        array $results
    ): void {
        $result = $this->chainOn($values, [], $accepted);

        $this->assertTrue($result->getFits());
        $this->assertSame($failed, $this->failed($result->getResults()));
        $this->assertSame($results, $this->items($result->getResults()));
    }

    public function testMapValuesWithListsOfDifferentLengthStopsTheChain(): void
    {
        $result = $this->chainOn(['L'], [
            $this->step(TransformationAllowList::MAP_VALUES, ['original' => 'S|M|L', 'converted' => 'small|medium']),
        ], [Types::DEFAULT_TYPE]);

        [$map] = $result->getSteps();
        $this->assertNull($map['resultType']);
        $this->assertStringContainsString('position 1 differ in length (3 and 2)', $map['error']);
        $this->assertSame([], $map['values']);
        $this->assertFalse($result->getFits());
        $this->assertSame([], $result->getResults());
    }

    public function testAColumnDoesNotGoIntoAnImageWithoutLoadingTheAsset(): void
    {
        $result = $this->chainOn(['/images/chair.jpg'], [], [Types::ASSET]);

        $this->assertFalse($result->getFits());
        $this->assertNull($result->getConversion());
        $this->assertSame([], $result->getResults());
        $this->assertSame(['trim', 'stringReplace', 'conditionalConversion', 'loadAsset'], $result->getOffered());
    }

    public function testATextFieldIsOfferedNeitherSplittingNorLoading(): void
    {
        $result = $this->chainOn(['chair'], [], [Types::DEFAULT_TYPE, Types::BOOLEAN]);

        $this->assertSame(self::TEXT_STEPS, $result->getOffered());
    }

    public function testARelationToObjectsLoadsThemFromAList(): void
    {
        $result = $this->chainOn(['1,2'], [], [Types::DATA_OBJECT_ARRAY]);

        // a single object is no list of them
        $this->assertSame([...self::TEXT_STEPS, 'explode'], $result->getOffered());

        $split = $this->chainOn(['1,2'], [$this->step(TransformationAllowList::SPLIT, ['delimiter' => ','])], [
            Types::DATA_OBJECT_ARRAY,
        ]);

        $this->assertFalse($split->getFits());
        $this->assertSame(
            ['trim', 'stringReplace', 'conditionalConversion', 'loadDataObject'],
            $split->getOffered()
        );
    }

    public function testAListIsNotSplitAgain(): void
    {
        $result = $this->chainOn(['a,b|c'], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => '|']),
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => ',']),
        ], [Types::DEFAULT_ARRAY]);

        [$first, $second] = $result->getSteps();
        $this->assertSame(Types::DEFAULT_ARRAY, $first['resultType']);
        $this->assertNull($second['resultType']);
        $this->assertStringContainsString('explode operator at transformation position 2', $second['error']);
        $this->assertFalse($result->getFits());
        $this->assertSame([], $result->getResults());
    }

    public function testAnIdThatIsNoWholeNumberFailsInItsRow(): void
    {
        $result = $this->chainOn(['1', '1,2', '1abc', ''], [
            $this->step(TransformationAllowList::LOAD_OBJECT),
        ], [Types::DATA_OBJECT]);

        $this->assertSame([false, true, true, false], $this->failed($result->getResults()));
        $this->assertSame([[self::ARTICLE], [], [], []], $this->items($result->getResults()));

        $listed = $this->chainOn(['1|2x', '1|3'], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => '|']),
            $this->step(TransformationAllowList::LOAD_ASSET),
        ], [Types::GALLERY]);

        $this->assertSame([true, false], $this->failed($listed->getResults()));

        $byPath = $this->chainOn(['/Products/Chair'], [
            $this->step(TransformationAllowList::LOAD_OBJECT, ['loadStrategy' => 'path']),
        ], [Types::DATA_OBJECT]);

        $this->assertSame([false], $this->failed($byPath->getResults()));
    }

    public function testAConvertedColumnIsStillOfferedTheTextSteps(): void
    {
        $result = $this->chainOn(['12,50'], [], [Types::NUMERIC, Types::BOOLEAN]);

        $this->assertSame('numeric', $result->getConversion());
        $this->assertSame(self::TEXT_STEPS, $result->getOffered());
    }

    public function testAGalleryTakesTheAssetsLoadedFromAList(): void
    {
        $result = $this->chainOn(['1|2', '9'], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => '|']),
            $this->step(TransformationAllowList::LOAD_ASSET),
        ], [Types::GALLERY]);

        $this->assertSame(Types::ASSET_ARRAY, $result->getSteps()[1]['resultType']);
        $this->assertSame([[self::IMAGE, '/images/2.jpg'], []], $this->items($result->getSteps()[1]['values']));
        $this->assertSame('gallery', $result->getConversion());
        $this->assertSame(Types::GALLERY, $result->getResultType());
        $this->assertSame([[self::IMAGE, '/images/2.jpg'], []], $this->items($result->getResults()));
        // nothing leads from a gallery back to the field
        $this->assertSame([], $result->getOffered());
    }

    public function testAnObjectTheUserMayNotSeeIsNotFound(): void
    {
        $result = $this->chainOn(['1', '2', '1,2'], [
            $this->step(TransformationAllowList::LOAD_OBJECT),
        ], [Types::DATA_OBJECT]);

        $this->assertSame([[self::ARTICLE], [], []], $this->items($result->getResults()));
        $this->assertSame([false, false, true], $this->failed($result->getResults()));

        $listed = $this->chainOn(['1,2'], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => ',']),
            $this->step(TransformationAllowList::LOAD_OBJECT),
        ], [Types::DATA_OBJECT_ARRAY]);

        $this->assertSame([[self::ARTICLE]], $this->items($listed->getResults()));
        $this->assertStringNotContainsString(self::HIDDEN_ARTICLE, json_encode($this->items($listed->getResults())));
    }

    public function testAnAssetTheUserMayNotSeeIsNotFound(): void
    {
        $result = $this->chainOn(['1', '3'], [$this->step(TransformationAllowList::LOAD_ASSET)], [Types::ASSET]);

        $this->assertSame([[self::IMAGE], []], $this->items($result->getResults()));
        $this->assertSame([false, false], $this->failed($result->getResults()));
        $this->assertStringNotContainsString(self::HIDDEN_IMAGE, (string) json_encode($result->getSteps()));

        $listed = $this->chainOn(['1|3'], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => '|']),
            $this->step(TransformationAllowList::LOAD_ASSET),
        ], [Types::GALLERY]);

        $this->assertSame([[self::IMAGE]], $this->items($listed->getResults()));
        $this->assertStringNotContainsString(
            self::HIDDEN_IMAGE,
            (string) json_encode($this->items($listed->getResults()))
        );
    }

    public function testARowAStepFailsOnIsLeftOutOfTheStepsAfterIt(): void
    {
        $result = $this->chainOn(['13', '1'], [
            $this->step(TransformationAllowList::LOAD_ASSET),
        ], [Types::GALLERY]);

        $this->assertSame([true, false], $this->failed($result->getSteps()[0]['values']));
        $this->assertSame('gallery', $result->getConversion());
        $this->assertSame([true, false], $this->failed($result->getResults()));
        $this->assertSame([[], [self::IMAGE]], $this->items($result->getResults()));
    }

    public function testAValueThePreviewCannotShowIsNamedByItsType(): void
    {
        $result = $this->chainOn(['99'], [$this->step(TransformationAllowList::LOAD_OBJECT)], [Types::DATA_OBJECT]);

        $this->assertSame([['stdClass']], $this->items($result->getResults()));
    }

    public function testAStepThatCannotTakeTheResultBeforeItStopsTheChain(): void
    {
        $result = $this->chainOn(['1'], [
            $this->step(TransformationAllowList::LOAD_ASSET),
            $this->step(TransformationAllowList::TRIM),
            $this->step(TransformationAllowList::REPLACE, ['search' => 'a', 'replace' => 'b']),
        ], [Types::ASSET]);

        [$load, $trim, $replace] = $result->getSteps();
        $this->assertSame(Types::ASSET, $load['resultType']);
        $this->assertNull($load['error']);
        $this->assertNull($trim['resultType']);
        $this->assertStringContainsString('trim operator at transformation position 2', $trim['error']);
        $this->assertSame([], $trim['values']);
        $this->assertNull($replace['resultType']);
        $this->assertNull($replace['error']);
        $this->assertFalse($result->getFits());
        $this->assertNull($result->getResultType());
        $this->assertSame([], $result->getResults());
        $this->assertSame([], $result->getOffered());
    }

    public function testAFieldTakesAColumnAsItIsWhenItsTextOrAConversionOfItFits(): void
    {
        $chain = $this->chain();

        $this->assertTrue($chain->takesColumnAsIs([Types::DEFAULT_TYPE]));
        $this->assertTrue($chain->takesColumnAsIs([Types::NUMERIC, Types::BOOLEAN]));
        $this->assertTrue($chain->takesColumnAsIs([Types::DATE]));
        $this->assertFalse($chain->takesColumnAsIs([Types::ASSET, Types::DATA_OBJECT]));
        $this->assertFalse($chain->takesColumnAsIs([Types::QUANTITY_VALUE]));
        $this->assertFalse($chain->takesColumnAsIs([]));
    }

    public function testEachFieldTakesEveryTypeItIsListedFor(): void
    {
        $types = $this->createStub(DataTypeServiceInterface::class);
        $types->method('loadClassAttributes')->willReturnCallback(
            function (string $classId, string $type, bool $systemRead, bool $systemWrite, bool $advanced) {
                $this->assertSame('PROD', $classId);
                $this->assertFalse($systemRead || $advanced);
                $this->assertTrue($systemWrite);

                return new ClassAttributesResponse(match ($type) {
                    Types::DEFAULT_TYPE => [$this->attribute('name')],
                    Types::BOOLEAN => [$this->attribute('name'), $this->attribute('active')],
                    Types::ASSET => [$this->attribute('related')],
                    Types::DATA_OBJECT => [$this->attribute('related')],
                    default => [],
                });
            }
        );

        $this->assertSame([
            'name' => [Types::DEFAULT_TYPE, Types::BOOLEAN],
            'active' => [Types::BOOLEAN],
            'related' => [Types::ASSET, Types::DATA_OBJECT],
        ], $this->chain($types)->acceptedTypes('PROD'));
    }

    public function testAStoreKeyTakesTheTypesListedForItsDataType(): void
    {
        $chain = $this->chain();

        $this->assertSame([Types::DEFAULT_TYPE, Types::BOOLEAN], $chain->acceptedTypesOf('input'));
        $this->assertSame([Types::NUMERIC, Types::BOOLEAN], $chain->acceptedTypesOf('numeric'));
        $this->assertSame([Types::DATE], $chain->acceptedTypesOf('datetime'));
        $this->assertSame([], $chain->acceptedTypesOf('quantityValue'));
    }

    public function testAColumnIsConvertedForItsFieldWithTheRowsItCannotTakeAndWhere(): void
    {
        $user = new User();
        $user->setId(4712);

        $converted = $this->chain()->convertColumn(
            ['1', '12abc', '', '2'],
            [$this->step('loadDataObject', ['loadStrategy' => 'id'])],
            [Types::DATA_OBJECT],
            $user
        );

        $this->assertNotNull($converted);
        $this->assertNull($converted->conversion);
        $this->assertInstanceOf(Concrete::class, $converted->values[0]);
        $this->assertSame([false, true, false, false], $converted->failedInSteps);
        $this->assertSame([false, true, false, false], $converted->failed);

        $numbers = $this->chain()->convertColumn(['1.5', '12,5,0', ''], [], [Types::NUMERIC], $user);
        $this->assertNotNull($numbers);
        $this->assertSame('numeric', $numbers->conversion);
        $this->assertSame(1.5, $numbers->values[0]);
        $this->assertNull($numbers->values[2], 'an empty cell stays empty');
        $this->assertSame([false, false, false], $numbers->failedInSteps);
        $this->assertSame([false, true, false], $numbers->failed);
    }

    public function testAConvertedColumnMarksTheRowsThatLostAnElementTheUserMayNotView(): void
    {
        $user = new User();
        $user->setId(4712);

        $single = $this->chain()->convertColumn(
            ['1', '2', '5', ''],
            [$this->step(TransformationAllowList::LOAD_OBJECT)],
            [Types::DATA_OBJECT],
            $user
        );
        $this->assertNotNull($single);
        $this->assertNull($single->values[1], 'the preview still shows it as not found');
        $this->assertSame([false, true, false, false], $single->hidden, 'an ID that finds nothing hides nothing');
        $this->assertSame([false, false, false, false], $single->failed);

        $listed = $this->chain()->convertColumn(
            ['1,2', '1'],
            [
                $this->step(TransformationAllowList::SPLIT, ['delimiter' => ',']),
                $this->step(TransformationAllowList::LOAD_OBJECT),
            ],
            [Types::DATA_OBJECT_ARRAY],
            $user
        );
        $this->assertNotNull($listed);
        $this->assertCount(1, $listed->values[0]);
        $this->assertSame([true, false], $listed->hidden);

        $gallery = $this->chain()->convertColumn(
            ['1|3', '1|2'],
            [
                $this->step(TransformationAllowList::SPLIT, ['delimiter' => '|']),
                $this->step(TransformationAllowList::LOAD_ASSET),
            ],
            [Types::GALLERY],
            $user
        );
        $this->assertNotNull($gallery);
        $this->assertSame('gallery', $gallery->conversion);
        $this->assertSame([true, false], $gallery->hidden, 'the mark outlasts the conversion after the step');
    }

    public function testAColumnThatCannotReachItsFieldIsNotConverted(): void
    {
        $user = new User();
        $user->setId(4712);

        $this->assertNull($this->chain()->convertColumn(['1'], [], [Types::DATA_OBJECT], $user));
        $this->assertNull($this->chain()->convertColumn(
            ['a,b'],
            [$this->step('explode', ['delimiter' => ',']), $this->step('explode', ['delimiter' => ','])],
            [Types::DEFAULT_ARRAY],
            $user
        ));
    }

    /**
     * @param list<string|null> $values
     * @param list<array{type: string, settings: array<string, string>}> $steps
     * @param list<string> $accepted
     */
    private function chainOn(array $values, array $steps, array $accepted): AdHocImportTransformationResponse
    {
        $user = new User();
        $user->setId(4712);

        return $this->chain()->run($values, $steps, $accepted, $user);
    }

    /**
     * @param array<string, string> $settings
     *
     * @return array{type: string, settings: array<string, string>}
     */
    private function step(string $type, array $settings = []): array
    {
        return TransformationAllowList::step(['type' => $type, 'settings' => $settings], 1);
    }

    /**
     * @param list<AdHocImportTransformationCell> $cells
     *
     * @return list<list<string>>
     */
    private function items(array $cells): array
    {
        return array_map(static fn (AdHocImportTransformationCell $cell): array => $cell->getItems(), $cells);
    }

    /**
     * @param list<AdHocImportTransformationCell> $cells
     *
     * @return list<bool>
     */
    private function failed(array $cells): array
    {
        return array_map(static fn (AdHocImportTransformationCell $cell): bool => $cell->getFailed(), $cells);
    }

    /**
     * @return array{key: string, title: string, localized: bool}
     */
    private function attribute(string $key): array
    {
        return ['key' => $key, 'title' => $key, 'localized' => false];
    }

    private function chain(?DataTypeServiceInterface $types = null): TransformationChain
    {
        return AdHocTransformations::chain($types ?? $this->createStub(DataTypeServiceInterface::class), [], [
            'loadAsset' => new AdHocStandInLoader(Types::ASSET, Types::ASSET_ARRAY, $this->image(...)),
            'loadDataObject' => new AdHocStandInLoader(
                Types::DATA_OBJECT,
                Types::DATA_OBJECT_ARRAY,
                $this->object(...)
            ),
        ]);
    }

    private function image(string $id): ?Image
    {
        if ($id === '13') {
            throw new \UnexpectedValueException('The asset cannot be read');
        }
        if (!in_array($id, ['1', '2', '3'], true)) {
            return null;
        }
        $image = $this->createStub(Image::class);
        $image->method('getRealFullPath')->willReturn($id === '3' ? self::HIDDEN_IMAGE : '/images/' . $id . '.jpg');
        $image->method('isAllowed')->willReturnCallback(
            static fn (string $type, ?User $user): bool => $type === 'view' && $user?->getId() === 4712 && $id !== '3'
        );

        return $image;
    }

    private function object(string $id): mixed
    {
        if ($id === '99') {
            return new \stdClass();
        }
        if (!in_array($id, ['1', '2'], true)) {
            return null;
        }
        $object = $this->createStub(Concrete::class);
        $object->method('getRealFullPath')->willReturn($id === '1' ? self::ARTICLE : self::HIDDEN_ARTICLE);
        $object->method('isAllowed')->willReturnCallback(
            static fn (string $type, ?User $user): bool => $type === 'view' && $user?->getId() === 4712 && $id === '1'
        );

        return $object;
    }
}
