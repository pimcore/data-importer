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
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Factory\AsNumericRange;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Model\DataObject\Data\NumericRange;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * The operators building a structured value out of a source row: the value each one produces, the
 * input type it takes and the result type it declares, which is what the mapping editor filters
 * the target field and the classification store key picker by.
 */
class StructuredValueOperatorTest extends Unit
{
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

    private function numericRange(): AsNumericRange
    {
        /** @var AsNumericRange $operator */
        $operator = $this->tester->grabService(AsNumericRange::class);

        return $operator;
    }
}
