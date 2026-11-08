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

use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\Element\ElementInterface;

/**
 * A transformation result as the texts of one preview cell.
 *
 * @internal
 */
final class CellItems
{
    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * @return list<string>
     */
    public static function of(mixed $value): array
    {
        if ($value instanceof ImageGallery) {
            $images = [];
            foreach ($value->getItems() as $item) {
                $images[] = $item?->getImage();
            }
            $value = $images;
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                $text = self::text($item);
                if ($text !== null) {
                    $items[] = $text;
                }
            }

            return $items;
        }

        $text = self::text($value);

        return $text === null ? [] : [$text];
    }

    private static function text(mixed $value): ?string
    {
        return match (true) {
            $value === null, $value === '' => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof ElementInterface => $value->getRealFullPath(),
            $value instanceof \DateTimeInterface => $value->format(self::DATE_FORMAT),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
            // nothing an allowed operator returns; named so a preview still shows there is a value
            default => get_debug_type($value),
        };
    }
}
