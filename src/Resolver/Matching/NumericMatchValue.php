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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Matching;

use Pimcore\Model\DataObject\ClassDefinition\Data\Numeric;

/**
 * The strict check of a value to match a numeric field by: the column would read `12abc` as 12 and `n/a` as 0, and a
 * number the field cannot hold finds nothing, which would make a second object.
 *
 * @internal
 */
final class NumericMatchValue
{
    // no exponent, no comma and no leading zero, as for an Object ID: `012` may not mean the number 12
    private const NUMBER_PATTERN = '/^([+-]?)(0|[1-9]\d*)(?:\.(\d+))?\z/';

    /**
     * @return string|null the number in one spelling (`+12.50` is `12.5`), or null if the field cannot hold the value
     */
    public static function normalize(string $value, Numeric $field): ?string
    {
        if (preg_match(self::NUMBER_PATTERN, $value, $parts) !== 1) {
            return null;
        }

        $whole = $parts[2];
        $fraction = rtrim($parts[3] ?? '', '0');
        $number = $whole . ($fraction === '' ? '' : '.' . $fraction);
        if ($parts[1] === '-' && $number !== '0') {
            $number = '-' . $number;
        }

        return self::fitsColumn($number, $whole, $fraction, $field) && self::isWithinLimits($number, $field)
            ? $number
            : null;
    }

    private static function fitsColumn(string $number, string $whole, string $fraction, Numeric $field): bool
    {
        if ($field->getInteger()) {
            return filter_var($number, FILTER_VALIDATE_INT) !== false;
        }

        $size = $field->getDecimalSize();
        $scale = $field->getDecimalPrecision();
        if ($size === null && $scale === null) {
            // more digits than a double keeps would compare equal to another number
            return strlen($whole . $fraction) <= PHP_FLOAT_DIG;
        }

        $size ??= Numeric::DECIMAL_SIZE_DEFAULT;
        $scale ??= Numeric::DECIMAL_PRECISION_DEFAULT;

        return strlen($fraction) <= $scale && strlen($whole) <= $size - $scale;
    }

    private static function isWithinLimits(string $number, Numeric $field): bool
    {
        $float = (float) $number;
        $minValue = $field->getMinValue();
        $maxValue = $field->getMaxValue();

        return !($field->getUnsigned() && $float < 0)
            && ($minValue === null || $float >= $minValue)
            && ($maxValue === null || $float <= $maxValue);
    }
}
