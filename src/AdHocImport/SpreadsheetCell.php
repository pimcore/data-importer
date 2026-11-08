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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

/**
 * Text from an uploaded file as a cell of a CSV file someone opens in a spreadsheet.
 *
 * @internal
 */
final class SpreadsheetCell
{
    /**
     * An apostrophe keeps a spreadsheet from reading the text as a formula; a number stays as it is.
     */
    public static function safe(string $text): string
    {
        if (is_numeric($text) || preg_match('/^(?:[\x00-\x20]*[=+\-@]|[\t\r])/', $text) !== 1) {
            return $text;
        }

        return "'" . $text;
    }
}
