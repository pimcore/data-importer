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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsExternalImage;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsNumericRange;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\QuantityValueRange as QuantityValueRangeOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Model\DataObject\Data\ExternalImage;
use Pimcore\Model\DataObject\Data\NumericRange;
use Pimcore\Model\DataObject\Data\QuantityValueRange;
use Pimcore\Model\DataObject\QuantityValue\Unit as QuantityValueUnit;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * The operators building a structured value out of a source row: the value each one produces, the
 * input type it takes and the result type it declares, which is what the mapping editor filters
 * the target field and the classification store key picker by.
 */
class StructuredValueOperatorTest extends Unit
{
    private const UNIT_ID = 'svo_mm';

    private const URL = 'https://example.com/a.png';

    private const UNIT_SOURCE = 'unitSourceSelect';

    private const UNIT_ABBREVIATION = 'svo-mm';

    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testNumericRangeTakesTwoSourcesAsItsBounds(): void
    {
        $operator = $this->numericRange();

        $result = $operator->process(['3', '7.5']);

        static::assertInstanceOf(NumericRange::class, $result);
        static::assertSame(3.0, $result->getMinimum());
        static::assertSame(7.5, $result->getMaximum());
    }

    public function testNumericRangeKeepsAHalfOpenRange(): void
    {
        $operator = $this->numericRange();

        $result = $operator->process(['3', '']);

        static::assertInstanceOf(NumericRange::class, $result);
        static::assertSame(3.0, $result->getMinimum());
        static::assertNull($result->getMaximum(), 'an empty bound is no bound, not zero');
    }

    public function testNumericRangeWritesNothingForAnEmptyRow(): void
    {
        static::assertNull($this->numericRange()->process(['', null]));
    }

    public function testNumericRangeDeclaresItsResultType(): void
    {
        static::assertSame(
            TransformationDataTypeService::NUMERIC_RANGE,
            $this->numericRange()->evaluateReturnType(TransformationDataTypeService::DEFAULT_ARRAY)
        );
    }

    /**
     * The control for the type declaration: a single column cannot be a range, so the pipeline has
     * to be refused rather than silently given one bound.
     */
    public function testNumericRangeRefusesASingleSource(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->numericRange()->evaluateReturnType(TransformationDataTypeService::DEFAULT_TYPE, 2);
    }

    public function testNumericRangePreviewNamesBothBounds(): void
    {
        $preview = $this->numericRange()->generateResultPreview(new NumericRange(3, 7));

        static::assertStringContainsString('3', $preview);
        static::assertStringContainsString('7', $preview);
    }

    public function testQuantityValueRangeTakesTwoBoundsAndAUnit(): void
    {
        $this->unit();
        $operator = $this->quantityValueRange();
        $operator->setSettings([]);

        $result = $operator->process(['3', '7.5', self::UNIT_ID]);

        static::assertInstanceOf(QuantityValueRange::class, $result);
        static::assertSame(3.0, $result->getMinimum());
        static::assertSame(7.5, $result->getMaximum());
        static::assertSame(self::UNIT_ID, $result->getUnitId());
    }

    public function testQuantityValueRangeResolvesAUnitByAbbreviation(): void
    {
        $this->unit();
        $operator = $this->quantityValueRange();
        $operator->setSettings([self::UNIT_SOURCE => 'abbr']);

        $result = $operator->process(['3', '7', self::UNIT_ABBREVIATION]);

        static::assertInstanceOf(QuantityValueRange::class, $result);
        static::assertSame(self::UNIT_ID, $result->getUnitId());
    }

    public function testQuantityValueRangeTakesAStaticUnit(): void
    {
        $this->unit();
        $operator = $this->quantityValueRange();
        $operator->setSettings([self::UNIT_SOURCE => 'static', 'staticUnitSelect' => self::UNIT_ID]);

        $result = $operator->process(['3', '7']);

        static::assertInstanceOf(QuantityValueRange::class, $result);
        static::assertSame(self::UNIT_ID, $result->getUnitId());
    }

    public function testQuantityValueRangeDropsAUnitTheRowDoesNotName(): void
    {
        $operator = $this->quantityValueRange();
        $operator->setSettings([]);

        $result = $operator->process(['3', '7', 'no-such-unit']);

        static::assertInstanceOf(QuantityValueRange::class, $result);
        static::assertNull($result->getUnitId(), 'an unknown unit id is dropped, not passed on');
    }

    public function testQuantityValueRangeWritesNothingForAnEmptyRow(): void
    {
        $operator = $this->quantityValueRange();
        $operator->setSettings([]);

        static::assertNull($operator->process(['', '', '']));
    }

    public function testQuantityValueRangeDeclaresItsResultType(): void
    {
        $operator = $this->quantityValueRange();
        $operator->setSettings([]);

        static::assertSame(
            TransformationDataTypeService::QUANTITY_VALUE_RANGE,
            $operator->evaluateReturnType(TransformationDataTypeService::DEFAULT_ARRAY)
        );
    }

    /**
     * The control: even with a static unit the two bounds still need two sources, so a single
     * column is refused here where the plain quantity value operator accepts one.
     */
    public function testQuantityValueRangeRefusesASingleSourceEvenWithAStaticUnit(): void
    {
        $operator = $this->quantityValueRange();
        $operator->setSettings([self::UNIT_SOURCE => 'static', 'staticUnitSelect' => self::UNIT_ID]);

        $this->expectException(InvalidConfigurationException::class);

        $operator->evaluateReturnType(TransformationDataTypeService::DEFAULT_TYPE, 1);
    }

    public function testExternalImageWrapsAUrl(): void
    {
        $result = $this->externalImage()->process(self::URL);

        static::assertInstanceOf(ExternalImage::class, $result);
        static::assertSame(self::URL, $result->getUrl());
    }

    public function testExternalImageWritesNothingForAnEmptyCell(): void
    {
        static::assertNull($this->externalImage()->process(''));
        static::assertNull($this->externalImage()->process(null));
    }

    public function testExternalImageDeclaresItsResultType(): void
    {
        static::assertSame(
            TransformationDataTypeService::EXTERNAL_IMAGE,
            $this->externalImage()->evaluateReturnType(TransformationDataTypeService::DEFAULT_TYPE)
        );
    }

    /**
     * The control: a URL is one cell, so a pipeline handing this operator a list is refused rather
     * than silently given the first element of it.
     */
    public function testExternalImageRefusesAListOfSources(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->externalImage()->evaluateReturnType(TransformationDataTypeService::DEFAULT_ARRAY, 3);
    }

    public function testExternalImagePreviewNamesTheUrl(): void
    {
        $preview = $this->externalImage()->generateResultPreview(new ExternalImage(self::URL));

        static::assertStringContainsString(self::URL, $preview);
    }

    private function unit(): QuantityValueUnit
    {
        $unit = QuantityValueUnit::getById(self::UNIT_ID);
        if ($unit instanceof QuantityValueUnit) {
            return $unit;
        }

        $unit = new QuantityValueUnit();
        $unit->setId(self::UNIT_ID);
        $unit->setAbbreviation(self::UNIT_ABBREVIATION);
        $unit->setLongname('Millimetre');
        $unit->save();

        return $unit;
    }

    // grabService() is typed on the class string it is handed, so no local annotation is needed

    private function quantityValueRange(): QuantityValueRangeOperator
    {
        return $this->tester->grabService(QuantityValueRangeOperator::class);
    }

    private function externalImage(): AsExternalImage
    {
        return $this->tester->grabService(AsExternalImage::class);
    }

    private function numericRange(): AsNumericRange
    {
        return $this->tester->grabService(AsNumericRange::class);
    }
}
