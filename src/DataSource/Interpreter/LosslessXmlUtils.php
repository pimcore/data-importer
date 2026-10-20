<?php declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use Symfony\Component\Config\Util\XmlUtils;

/**
 * Symfony's XmlUtils types every numeric looking text, which changes values such as `0123` (octal 83),
 * `1E5` (float 100000) or `603E709` (INF, which cannot be JSON encoded). This variant keeps the original text
 * whenever the typed value would not print as exactly the same text. Only numbers are affected: the boolean and
 * null typing of `XmlUtils` (`true`, `FALSE`, `null`, ...) is unchanged.
 *
 * @internal
 */
final class LosslessXmlUtils extends XmlUtils
{
    public static function phpize(string|\Stringable $value): mixed
    {
        $text = (string) $value;
        $typed = parent::phpize($text);

        if ((\is_int($typed) || \is_float($typed)) && (string) $typed !== $text) {
            return $text;
        }

        return $typed;
    }
}
