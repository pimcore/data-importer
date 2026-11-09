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
 * Rows as the text of a CSV file.
 *
 * @internal
 */
final class CsvText
{
    /**
     * @param iterable<array<int, int|string|null>> $lines
     */
    public static function of(iterable $lines): string
    {
        $csv = fopen('php://temp', 'r+b');
        foreach ($lines as $line) {
            fputcsv($csv, $line, escape: '');
        }
        rewind($csv);
        $content = (string) stream_get_contents($csv);
        fclose($csv);

        return $content;
    }
}
