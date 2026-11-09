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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

/**
 * A row a run could not save.
 *
 * @phpstan-type Stored array{
 *     row: int,
 *     matchValue: string,
 *     action: string|null,
 *     message: string,
 *     values: list<string>
 * }
 *
 * @internal
 */
final readonly class RowFailure
{
    /**
     * @param int $row the row's number in the file, the header row counted
     * @param list<string> $values the row's cells
     */
    public function __construct(
        public int $row,
        public string $matchValue,
        public ?string $action,
        public string $message,
        public array $values,
    ) {
    }

    /**
     * @param Stored $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            $stored['row'],
            $stored['matchValue'],
            $stored['action'],
            $stored['message'],
            $stored['values']
        );
    }

    /**
     * @return Stored
     */
    public function toArray(): array
    {
        return [
            'row' => $this->row,
            'matchValue' => $this->matchValue,
            'action' => $this->action,
            'message' => $this->message,
            'values' => $this->values,
        ];
    }
}
