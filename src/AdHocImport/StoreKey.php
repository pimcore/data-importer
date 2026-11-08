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
 * A key of a classification store, in one of its groups.
 *
 * @internal
 */
final readonly class StoreKey
{
    private const string PATTERN = '/^([1-9]\d*)-([1-9]\d*)$/';

    public function __construct(
        public int $groupId,
        public int $keyId,
    ) {
    }

    /**
     * The key as the Classificationstore data target names it: `<group id>-<key id>`; null for anything else.
     */
    public static function fromId(mixed $id): ?self
    {
        if (!is_string($id) || preg_match(self::PATTERN, $id, $ids) !== 1) {
            return null;
        }

        return new self((int) $ids[1], (int) $ids[2]);
    }

    public function id(): string
    {
        return $this->groupId . '-' . $this->keyId;
    }
}
