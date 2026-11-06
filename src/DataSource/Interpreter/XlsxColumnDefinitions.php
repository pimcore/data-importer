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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * What a worksheet's `<cols>` says holds no data, yet the reader expands each range: it widens the grid the rows are
 * read as, a read filter is asked about every column of every row, and a range running backwards never ends.
 *
 * @internal
 */
final class XlsxColumnDefinitions
{
    public static function ignoreOn(Xlsx $reader): void
    {
        $reader->getSecurityScannerOrThrow()->setAdditionalCallback(
            static fn (string $xml): string => self::withoutColumnDefinitions($xml)
        );
    }

    // the scanner has refused a DOCTYPE by now, so parsing here resolves no entity
    private static function withoutColumnDefinitions(string $xml): string
    {
        if (!str_contains($xml, 'cols')) {
            return $xml;
        }

        $document = new \DOMDocument();
        if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return $xml; // the reader reports what is broken
        }

        $definitions = iterator_to_array($document->getElementsByTagNameNS('*', 'cols'));
        if ($definitions === []) {
            return $xml;
        }

        foreach ($definitions as $definition) {
            $definition->parentNode?->removeChild($definition);
        }

        return (string)$document->saveXML();
    }
}
