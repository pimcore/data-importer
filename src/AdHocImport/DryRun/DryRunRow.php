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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

/**
 * What an import would do with one row of the file.
 *
 * @internal
 */
final readonly class DryRunRow
{
    public const string UPDATE = 'update';

    public const string CREATE = 'create';

    /**
     * @param int $row the row's number in the file, the header row counted
     * @param string|null $action UPDATE or CREATE; null if the row means no object, which skips it in any case
     * @param string|null $error why the row means no object
     * @param list<array{old: string|null, new: string|null}> $values per field shown, the value before and after
     * @param list<array{field: int|null, label: string, value: string, message: string}> $invalid the values that
     *     cannot go into their field, which is named by its position among those shown, null for one not shown
     */
    public function __construct(
        public int $row,
        public string $matchValue,
        public ?string $action,
        public ?string $error = null,
        public array $values = [],
        public array $invalid = [],
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->action === null || $this->invalid !== [];
    }
}
