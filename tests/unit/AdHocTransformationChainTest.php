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
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationAllowList;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsArray;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Boolean;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Date;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Gallery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\Numeric;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\ConditionalConversion;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\Explode;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\StringReplace;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\Trim;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService as Types;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationCell;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportTransformationResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

class AdHocTransformationChainTest extends Unit
{
    private const string ARTICLE = '/Products/Chair';

    private const string HIDDEN_ARTICLE = '/Secret/Prototype';

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
        yield 'a date for a date field' => [[Types::DATE], 'date', Types::DATE, ['2026-11-07'], [['2026-11-07']]];
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

    public function testAValueTheConversionCannotReadFailsInItsRowOnly(): void
    {
        $result = $this->chainOn(['2026-11-07', '07.11.2026', ''], [], [Types::DATE]);

        $this->assertSame([false, true, false], $this->failed($result->getResults()));
        $this->assertSame([['2026-11-07'], [], []], $this->items($result->getResults()));
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

        $this->assertSame(['trim', 'stringReplace', 'conditionalConversion'], $result->getOffered());
    }

    public function testARelationToObjectsLoadsThemFromAList(): void
    {
        $result = $this->chainOn(['1,2'], [], [Types::DATA_OBJECT_ARRAY]);

        // a single object is no list of them
        $this->assertSame(['trim', 'stringReplace', 'conditionalConversion', 'explode'], $result->getOffered());

        $split = $this->chainOn(['1,2'], [$this->step(TransformationAllowList::SPLIT, ['delimiter' => ','])], [
            Types::DATA_OBJECT_ARRAY,
        ]);

        $this->assertFalse($split->getFits());
        $this->assertSame(
            ['trim', 'stringReplace', 'conditionalConversion', 'explode', 'loadDataObject'],
            $split->getOffered()
        );
    }

    public function testAConvertedColumnIsStillOfferedTheTextSteps(): void
    {
        $result = $this->chainOn(['12,50'], [], [Types::NUMERIC, Types::BOOLEAN]);

        $this->assertSame('numeric', $result->getConversion());
        $this->assertSame(['trim', 'stringReplace', 'conditionalConversion'], $result->getOffered());
    }

    public function testAGalleryTakesTheAssetsLoadedFromAList(): void
    {
        $result = $this->chainOn(['1|2', '9'], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => '|']),
            $this->step(TransformationAllowList::LOAD_ASSET),
        ], [Types::GALLERY]);

        $this->assertSame(Types::ASSET_ARRAY, $result->getSteps()[1]['resultType']);
        $this->assertSame([['/images/1.jpg', '/images/2.jpg'], []], $this->items($result->getSteps()[1]['values']));
        $this->assertSame('gallery', $result->getConversion());
        $this->assertSame(Types::GALLERY, $result->getResultType());
        $this->assertSame([['/images/1.jpg', '/images/2.jpg'], []], $this->items($result->getResults()));
        // nothing leads from a gallery back to the field
        $this->assertSame([], $result->getOffered());
    }

    public function testAnObjectTheUserMayNotSeeIsNotFound(): void
    {
        $result = $this->chainOn(['1', '2', '1,2'], [
            $this->step(TransformationAllowList::LOAD_OBJECT),
        ], [Types::DATA_OBJECT]);

        $this->assertSame([[self::ARTICLE], [], []], $this->items($result->getResults()));
        $this->assertSame([false, false, false], $this->failed($result->getResults()));

        $listed = $this->chainOn(['1,2'], [
            $this->step(TransformationAllowList::SPLIT, ['delimiter' => ',']),
            $this->step(TransformationAllowList::LOAD_OBJECT),
        ], [Types::DATA_OBJECT_ARRAY]);

        $this->assertSame([[self::ARTICLE]], $this->items($listed->getResults()));
        $this->assertStringNotContainsString(self::HIDDEN_ARTICLE, json_encode($this->items($listed->getResults())));
    }

    public function testARowAStepFailsOnIsLeftOutOfTheStepsAfterIt(): void
    {
        $result = $this->chainOn(['broken', '1'], [$this->step(TransformationAllowList::LOAD_ASSET)], [Types::GALLERY]);

        $this->assertSame([true, false], $this->failed($result->getSteps()[0]['values']));
        $this->assertSame('gallery', $result->getConversion());
        $this->assertSame([true, false], $this->failed($result->getResults()));
        $this->assertSame([[], ['/images/1.jpg']], $this->items($result->getResults()));
    }

    public function testAValueThePreviewCannotShowIsNamedByItsType(): void
    {
        $result = $this->chainOn(['odd'], [$this->step(TransformationAllowList::LOAD_OBJECT)], [Types::DATA_OBJECT]);

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

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function stepsAnAdHocImportDoesNotOffer(): iterable
    {
        yield 'combining columns' => [['type' => 'combine']];
        yield 'importing files' => [['type' => 'importAsset']];
        yield 'no type' => [['settings' => []]];
        yield 'no object' => ['trim'];
        yield 'loading an object by an attribute' => [['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'attribute']]];
        yield 'loading unpublished objects' => [['type' => 'loadDataObject', 'settings' => ['loadUnpublished' => true]]];
        yield 'a setting of another operator' => [['type' => 'trim', 'settings' => ['delimiter' => ',']]];
        yield 'a setting that is no text' => [['type' => 'explode', 'settings' => ['delimiter' => [',']]]];
        yield 'settings that are no object' => [['type' => 'trim', 'settings' => 'both']];
        yield 'a trim mode there is none of' => [['type' => 'trim', 'settings' => ['mode' => 'middle']]];
    }

    /**
     * @dataProvider stepsAnAdHocImportDoesNotOffer
     */
    public function testAStepOutsideTheAllowListIsRefused(mixed $step): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transformation 3');

        TransformationAllowList::step($step, 3);
    }

    public function testAStepKeepsItsSettingsAndTheLoadingOnesDefaultToTheId(): void
    {
        $this->assertSame(
            ['type' => 'stringReplace', 'settings' => ['search' => ',', 'replace' => '.']],
            TransformationAllowList::step(['type' => 'stringReplace', 'settings' => ['search' => ',', 'replace' => '.']], 1)
        );
        $this->assertSame(
            ['type' => 'loadAsset', 'settings' => ['loadStrategy' => 'id']],
            TransformationAllowList::step(['type' => 'loadAsset'], 1)
        );
        $this->assertSame(
            ['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'path']],
            TransformationAllowList::step(['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'path']], 1)
        );
        $this->assertSame(['type' => 'trim', 'settings' => []], TransformationAllowList::step(['type' => 'trim'], 1));
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
        $logger = $this->createStub(ApplicationLogger::class);

        $operators = [
            'trim' => new Trim($logger),
            'stringReplace' => new StringReplace($logger),
            'conditionalConversion' => new ConditionalConversion($logger),
            'explode' => new Explode($logger),
            'loadAsset' => $this->loader(Types::ASSET, Types::ASSET_ARRAY, fn (string $id): ?ElementInterface => $this->image($id)),
            'loadDataObject' => $this->loader(
                Types::DATA_OBJECT,
                Types::DATA_OBJECT_ARRAY,
                fn (string $id): mixed => $this->object($id)
            ),
            'numeric' => new Numeric($logger),
            'date' => new Date($logger),
            'asArray' => new AsArray($logger),
            'gallery' => new Gallery($logger),
            'boolean' => new Boolean($logger),
        ];

        return new TransformationChain(
            new MappingConfigurationFactory(new MappingConfiguration(), $operators, []),
            $types ?? $this->createStub(DataTypeServiceInterface::class)
        );
    }

    /**
     * Stands in for a load operator, which needs the database: loads by ID as the real one does.
     *
     * @param \Closure(string): mixed $load
     */
    private function loader(string $type, string $arrayType, \Closure $load): AbstractOperator
    {
        return new class($this->createStub(ApplicationLogger::class), $type, $arrayType, $load) extends AbstractOperator {
            public function __construct(
                ApplicationLogger $logger,
                private readonly string $type,
                private readonly string $arrayType,
                private readonly \Closure $load
            ) {
                parent::__construct($logger);
            }

            public function process($inputData, bool $dryRun = false)
            {
                if (is_array($inputData)) {
                    return array_values(array_filter(array_map($this->load, $inputData)));
                }

                return ($this->load)((string) $inputData);
            }

            public function evaluateReturnType(string $inputType, ?int $index = null): string
            {
                return match ($inputType) {
                    Types::DEFAULT_TYPE => $this->type,
                    Types::DEFAULT_ARRAY => $this->arrayType,
                    default => throw new \Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException(
                        'Unsupported input type'
                    ),
                };
            }
        };
    }

    private function image(string $id): ?Image
    {
        if ($id === 'broken') {
            throw new \RuntimeException('The asset cannot be read');
        }
        if (!in_array($id, ['1', '2'], true)) {
            return null;
        }
        $image = $this->createStub(Image::class);
        $image->method('getRealFullPath')->willReturn('/images/' . $id . '.jpg');
        $image->method('isAllowed')->willReturn(true);

        return $image;
    }

    private function object(string $id): mixed
    {
        if ($id === 'odd') {
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
