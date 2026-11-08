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

use Pimcore\Model\DataObject\Data\ElementMetadata;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\DataObject\Data\Link;
use Pimcore\Model\DataObject\Data\ObjectMetadata;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

/**
 * A field's value as one line of text, to show what an import changes.
 *
 * @internal
 */
final class ValueText
{
    private const string DATE_FORMAT = 'Y-m-d';

    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private const string ITEM_SEPARATOR = ', ';

    /**
     * @param User|null $viewer who reads the text: an element they may not view is left out; null for no such limit
     */
    public static function of(mixed $value, ?User $viewer = null): ?string
    {
        if ($value instanceof ImageGallery) {
            $value = array_map(static fn (?Hotspotimage $item): mixed => $item?->getImage(), $value->getItems());
        }

        if (is_array($value)) {
            $items = array_filter(
                array_map(static fn (mixed $item): ?string => self::of($item, $viewer), $value),
                static fn (?string $item): bool => $item !== null
            );

            return $items === [] ? null : implode(self::ITEM_SEPARATOR, $items);
        }

        return match (true) {
            $value === null, $value === '' => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof ElementInterface => self::mayView($value, $viewer) ? $value->getRealFullPath() : null,
            $value instanceof ObjectMetadata => self::of($value->getObject(), $viewer),
            $value instanceof ElementMetadata => self::of($value->getElement(), $viewer),
            $value instanceof Hotspotimage => self::of($value->getImage(), $viewer),
            // its string is an HTML anchor
            $value instanceof Link => self::mayView($value->getElement(), $viewer) ? $value->getHref() : null,
            $value instanceof \DateTimeInterface => $value->format(self::DATE_FORMAT),
            $value instanceof \Stringable => (string) $value,
            default => get_debug_type($value),
        };
    }

    /**
     * For a field that holds a time as well, where two values of one day differ.
     */
    public static function ofDateTime(\DateTimeInterface $value): string
    {
        return $value->format(self::DATE_TIME_FORMAT);
    }

    private static function mayView(?ElementInterface $element, ?User $viewer): bool
    {
        return $element === null || $viewer === null || $element->isAllowed('view', $viewer);
    }
}
