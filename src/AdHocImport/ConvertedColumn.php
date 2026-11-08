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
 * One column's values as its field takes them, per row of the file.
 *
 * @internal
 */
final readonly class ConvertedColumn
{
    /**
     * @param list<mixed> $values the value for the field; null for an empty cell and a failed row
     * @param list<bool> $failedInSteps the transformations could not take the row's value
     * @param list<bool> $failed the transformations or the conversion could not take it
     * @param string|null $conversion the operator converting into the field's type; null if none is needed
     */
    public function __construct(
        public array $values,
        public array $failedInSteps,
        public array $failed,
        public ?string $conversion,
    ) {
    }

    /**
     * A column whose field takes no value an import can lead to: each value fails, an empty cell clears the field.
     *
     * @param list<?string> $values
     */
    public static function unwritable(array $values): self
    {
        $failed = array_map(static fn (?string $value): bool => $value !== null && $value !== '', $values);

        return new self(array_fill(0, count($values), null), $failed, $failed, null);
    }
}
