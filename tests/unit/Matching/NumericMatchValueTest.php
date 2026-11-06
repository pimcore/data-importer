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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Matching;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\NumericMatchValue;
use Pimcore\Model\DataObject\ClassDefinition\Data\Numeric;

class NumericMatchValueTest extends Unit
{
    public static function spellings(): array
    {
        return [
            'a whole number' => ['12', '12'],
            'zero' => ['0', '0'],
            'a plus sign' => ['+12', '12'],
            'a minus sign' => ['-12', '-12'],
            'negative zero' => ['-0', '0'],
            'negative zero with decimals' => ['-0.00', '0'],
            'decimals' => ['12.5', '12.5'],
            'trailing zeros' => ['12.50', '12.5'],
            'only zeros after the point' => ['12.0', '12'],
            'below one' => ['0.5', '0.5'],
        ];
    }

    /**
     * @dataProvider spellings
     */
    public function testANumberHasOneSpelling(string $value, string $expected): void
    {
        $this->assertSame($expected, NumericMatchValue::normalize($value, new Numeric()));
    }

    public static function valuesThatAreNoNumber(): array
    {
        return [
            'text after the digits' => ['12abc'],
            'text before the digits' => ['abc12'],
            'no digits' => ['n/a'],
            'an exponent' => ['1e3'],
            'a decimal comma' => ['12,5'],
            'a thousands separator' => ['1,000'],
            'a leading zero' => ['012'],
            'a point at the end' => ['12.'],
            'a point at the start' => ['.5'],
            'two points' => ['1.2.3'],
            'two signs' => ['--1'],
            'a blank inside' => ['1 000'],
            'a line break at the end' => ["12\n"],
            'hexadecimal' => ['0x1A'],
            'infinity' => ['INF'],
            'digits of another script' => ['١٢'],
        ];
    }

    /**
     * @dataProvider valuesThatAreNoNumber
     */
    public function testAValueThatIsNoNumberIsRefused(string $value): void
    {
        $this->assertNull(NumericMatchValue::normalize($value, new Numeric()));
    }

    public function testAnIntegerFieldTakesOnlyWholeNumbersItsColumnHolds(): void
    {
        $field = new Numeric();
        $field->setInteger(true);

        $this->assertSame('12', NumericMatchValue::normalize('12', $field));
        $this->assertSame('12', NumericMatchValue::normalize('12.0', $field));
        $this->assertSame((string) PHP_INT_MAX, NumericMatchValue::normalize((string) PHP_INT_MAX, $field));
        $this->assertNull(NumericMatchValue::normalize('12.5', $field));
        $this->assertNull(NumericMatchValue::normalize('9223372036854775808', $field));
    }

    public function testADecimalFieldTakesWhatItsColumnHolds(): void
    {
        $field = new Numeric();
        $field->setDecimalSize(5);
        $field->setDecimalPrecision(2);

        $this->assertSame('999.99', NumericMatchValue::normalize('999.99', $field));
        $this->assertSame('999.9', NumericMatchValue::normalize('999.900', $field));
        $this->assertNull(NumericMatchValue::normalize('999.999', $field));
        $this->assertNull(NumericMatchValue::normalize('1000', $field));
    }

    public function testADecimalFieldOfOnlyDecimalsTakesNumbersBelowOne(): void
    {
        $field = new Numeric();
        $field->setDecimalSize(2);
        $field->setDecimalPrecision(2);

        $this->assertSame('0.5', NumericMatchValue::normalize('0.5', $field));
        $this->assertSame('0.99', NumericMatchValue::normalize('0.99', $field));
        $this->assertSame('0', NumericMatchValue::normalize('0', $field));
        $this->assertNull(NumericMatchValue::normalize('1', $field));
        $this->assertNull(NumericMatchValue::normalize('0.999', $field));
    }

    public function testADecimalFieldWithOnlyAPrecisionTakesCoresDefaultSize(): void
    {
        $field = new Numeric();
        $field->setDecimalPrecision(2);

        $this->assertSame(
            str_repeat('9', Numeric::DECIMAL_SIZE_DEFAULT - 2),
            NumericMatchValue::normalize(str_repeat('9', Numeric::DECIMAL_SIZE_DEFAULT - 2), $field)
        );
        $this->assertNull(
            NumericMatchValue::normalize(str_repeat('9', Numeric::DECIMAL_SIZE_DEFAULT - 1), $field)
        );
    }

    public function testADecimalFieldWithOnlyASizeHoldsNoDecimals(): void
    {
        $field = new Numeric();
        $field->setDecimalSize(5);

        $this->assertSame('99999', NumericMatchValue::normalize('99999', $field));
        $this->assertNull(NumericMatchValue::normalize('1.5', $field));
        $this->assertNull(NumericMatchValue::normalize('100000', $field));
    }

    public function testADoubleFieldRefusesMoreDigitsThanADoubleKeeps(): void
    {
        $this->assertSame('123456789012345', NumericMatchValue::normalize('123456789012345', new Numeric()));
        $this->assertSame('1234567890.12345', NumericMatchValue::normalize('1234567890.12345', new Numeric()));
        $this->assertNull(NumericMatchValue::normalize('1234567890123456', new Numeric()));
        $this->assertNull(NumericMatchValue::normalize('1234567890.123456', new Numeric()));
    }

    public function testADoubleFieldDoesNotCountTheZeroOfANumberBelowOne(): void
    {
        $this->assertSame('0.123456789012345', NumericMatchValue::normalize('0.123456789012345', new Numeric()));
        $this->assertNull(NumericMatchValue::normalize('0.1234567890123456', new Numeric()));
    }

    public function testAnUnsignedFieldRefusesANegativeNumberButNotNegativeZero(): void
    {
        $field = new Numeric();
        $field->setUnsigned(true);

        $this->assertSame('5', NumericMatchValue::normalize('5', $field));
        $this->assertSame('0', NumericMatchValue::normalize('-0', $field));
        $this->assertNull(NumericMatchValue::normalize('-5', $field));
    }

    public function testTheFieldsLimitsCountAndIncludeThemselves(): void
    {
        $field = new Numeric();
        $field->setMinValue(10);
        $field->setMaxValue(20);

        $this->assertSame('10', NumericMatchValue::normalize('10', $field));
        $this->assertSame('20', NumericMatchValue::normalize('20', $field));
        $this->assertNull(NumericMatchValue::normalize('9.5', $field));
        $this->assertNull(NumericMatchValue::normalize('20.5', $field));
    }
}
