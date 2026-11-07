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

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Language;
use Pimcore\Model\DataObject\ClassDefinition\Data\Select;
use Pimcore\Model\DataObject\ClassDefinition\Data\User;
use Pimcore\Model\Element\ValidationException;

/**
 * The check of a value to match a text field by: one the field cannot hold finds nothing and would only fail when the
 * new object is saved, and an unpublished one is saved without its regex being checked at all.
 *
 * @internal
 */
final class TextMatchValue
{
    public static function fits(string $value, Data $field): bool
    {
        if ($field instanceof Select) {
            return self::isOption($value, $field);
        }

        try {
            $field->checkValidity($value);
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    /**
     * Not the field's checkValidity(), which looks at the options only if the field enforces them, and core's default
     * is that it does not.
     */
    private static function isOption(string $value, Select $field): bool
    {
        if (mb_strlen($value) > $field->getColumnLength()) {
            return false;
        }

        // core lists these only in admin, and for them no options means no value is valid, not that any is
        if ($field instanceof User || $field instanceof Language) {
            if (!$field->getOptions()) {
                $field->configureOptions();
            }

            return self::isAmong($value, $field->getOptions() ?? []);
        }

        if ($field->getOptions() === null) {
            $field->enrichFieldDefinition();
        }

        $options = $field->getOptions();
        // a provider can offer each object other options, and which object a row means is not known yet
        if (!$options || $field->dynamicOptions) {
            return true;
        }

        return self::isAmong($value, $options);
    }

    /**
     * @param array<int, array{key: string, value: mixed}> $options
     */
    private static function isAmong(string $value, array $options): bool
    {
        return in_array($value, array_map(strval(...), array_column($options, 'value')), true);
    }
}
