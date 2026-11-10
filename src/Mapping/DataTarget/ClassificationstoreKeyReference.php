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

    /**
     * One half of the integer pair: a row id, which is digits and nothing else.
     */
    private const INTEGER_ID = '/^[0-9]+$/';

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

        // exactly two, in either spelling: a third part cannot be assigned to either half, and
        // reading only the first two silently addresses a key the entry did not name
        if (count($keyParts) !== 2) {
            return null;
        }

        if ($keyParts[0] === '' || $keyParts[1] === '') {
            return null;
        }

        // an integer pair that is not a pair of integers names no key: both halves are cast to int
        // by whoever reads them, and a cast of a non-integer half addresses a key the token never
        // named - group 0 for `abc`, but also group 1 for `1.5` and group 100 for `1e2`. Digits
        // only, so that a decimal, a scientific notation, a sign or leading whitespace - all of
        // which `is_numeric()` accepts - is refused here rather than silently truncated later.
        if (!$isIdentifier && (!self::isIntegerId($keyParts[0]) || !self::isIntegerId($keyParts[1]))) {
            return null;
        }

        return new self($token, $reference, $keyParts[0], $keyParts[1], $isIdentifier, $variant);
    }

    private static function isIntegerId(string $half): bool
    {
        return preg_match(self::INTEGER_ID, $half) === 1;
    }
}
