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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

/**
 * A group and key reference of a classification store mapping entry: `<GROUP_IDENTIFIER>:<KEY_IDENTIFIER>`,
 * or the integer pair `<GROUP_ID>-<KEY_ID>` older entries carry, each optionally followed by `#<VARIANT>`.
 *
 * @internal
 */
final class ClassificationstoreKeyReference
{
    private const VARIANT_SEPARATOR = '#';

    private const IDENTIFIER_SEPARATOR = ':';

    private const INTEGER_SEPARATOR = '-';

    private function __construct(
        public readonly string $token,
        public readonly string $reference,
        public readonly string $group,
        public readonly string $key,
        public readonly bool $isIdentifier,
        public readonly ?string $variant
    ) {
    }

    /**
     * Null when the token is neither an identifier pair nor an integer pair.
     */
    public static function parse(string $token): ?self
    {
        $parts = explode(self::VARIANT_SEPARATOR, $token, 2);
        $reference = $parts[0];
        $variant = $parts[1] ?? null;
        if ($variant === '') {
            return null;
        }

        $isIdentifier = str_contains($reference, self::IDENTIFIER_SEPARATOR);
        $keyParts = explode($isIdentifier ? self::IDENTIFIER_SEPARATOR : self::INTEGER_SEPARATOR, $reference);
        if (count($keyParts) < 2 || ($isIdentifier && count($keyParts) !== 2)) {
            return null;
        }

        if ($keyParts[0] === '' || $keyParts[1] === '') {
            return null;
        }

        return new self($token, $reference, $keyParts[0], $keyParts[1], $isIdentifier, $variant);
    }
}
