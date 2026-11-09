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
 * A row written into a run's branch: which object it is there, and what the run needs to tell about it later.
 *
 * @phpstan-type Stored array{
 *     row: int,
 *     objectId: int|null,
 *     token: string|null,
 *     matchValue: string,
 *     action: string,
 *     key: string,
 *     folder: string,
 *     values: list<string>,
 *     data: array<int|string, mixed>
 * }
 *
 * @internal
 */
final readonly class RecordedRow
{
    /**
     * @param int $row the row's number in the file, the header row counted
     * @param int|null $objectId the existing object it changes
     * @param string|null $token the branch's name for the object it creates
     * @param string $folder where the object is, or goes
     * @param list<string> $values the row's cells
     * @param array<int|string, mixed> $data the row as the file has it
     */
    public function __construct(
        public int $row,
        public ?int $objectId,
        public ?string $token,
        public string $matchValue,
        public string $action,
        public string $key,
        public string $folder,
        public array $values,
        public array $data,
    ) {
    }

    public static function tokenOf(int $row): string
    {
        return 'row-' . $row;
    }

    public function isNew(): bool
    {
        return $this->action === 'create';
    }

    /**
     * @param Stored $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            $stored['row'],
            $stored['objectId'],
            $stored['token'],
            $stored['matchValue'],
            $stored['action'],
            $stored['key'],
            $stored['folder'],
            $stored['values'],
            $stored['data']
        );
    }

    /**
     * @return Stored
     */
    public function toArray(): array
    {
        return [
            'row' => $this->row,
            'objectId' => $this->objectId,
            'token' => $this->token,
            'matchValue' => $this->matchValue,
            'action' => $this->action,
            'key' => $this->key,
            'folder' => $this->folder,
            'values' => $this->values,
            'data' => $this->data,
        ];
    }

    public function failure(string $message): RowFailure
    {
        return new RowFailure($this->row, $this->matchValue, $this->action, $message, $this->values);
    }
}
