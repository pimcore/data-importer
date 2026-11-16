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

namespace Pimcore\Bundle\DataImporterBundle\ChangeControl;

use function array_is_list;
use function array_map;
use function is_array;
use function ksort;

/**
 * Key order differs between a stored and a merged document; a type change ("22" -> 22) still counts.
 *
 * @internal
 */
final class KeySorted
{
    public static function of(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $value = array_map(self::of(...), $value);
        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
