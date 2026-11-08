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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;

/**
 * One run of an ad hoc import: what it was started with and how far it got. A file has one run, under the file's id.
 *
 * @internal
 */
final readonly class RunRecord
{
    public const string RUNNING = 'running';

    public const string DONE = 'done';

    // a queue item's config name is at most 80 characters, and a run has no configuration to name
    private const string QUEUE_PREFIX = 'ad-hoc-import:';

    /**
     * @param array<string, mixed> $parameters what the dry run was asked with, by name
     * @param array{configuration: array<mixed>, mapping: array<int|string, mixed>}|null $configuration the
     *     configuration as it was when the run started, so a later change does not touch the run
     * @param int $total the rows the run imports
     * @param int $skipped the rows the preview's choice left out
     */
    public function __construct(
        public int $userId,
        public string $fileId,
        public FileType $type,
        public string $fileName,
        public array $parameters,
        public ?array $configuration,
        public string $errorMode,
        public int $total,
        public int $skipped,
        public string $matchColumn,
        public string $newObjectsFolder,
        public int $newObjectsFolderId,
        public string $userName,
        public int $updated = 0,
        public int $created = 0,
        public int $failed = 0,
        public string $state = self::RUNNING,
        public ?int $finishedAt = null,
    ) {
    }

    public static function queueName(string $fileId): string
    {
        return self::QUEUE_PREFIX . $fileId;
    }

    public static function fileIdOf(string $queueName): string
    {
        return substr($queueName, strlen(self::QUEUE_PREFIX));
    }

    public function processed(): int
    {
        return $this->updated + $this->created + $this->failed;
    }

    public function isRunning(): bool
    {
        return $this->state === self::RUNNING;
    }

    public function isComplete(): bool
    {
        return $this->processed() >= $this->total;
    }

    public function counted(RowOutcome $outcome): self
    {
        return self::fromArray([...$this->toArray(), $outcome->result => $this->{$outcome->result} + 1]);
    }

    public function finished(int $time): self
    {
        return self::fromArray([...$this->toArray(), 'state' => self::DONE, 'finishedAt' => $time]);
    }

    /**
     * @param array<string, mixed> $stored what toArray() gave
     */
    public static function fromArray(array $stored): self
    {
        return new self(...[...$stored, 'type' => FileType::from($stored['type'])]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...get_object_vars($this), 'type' => $this->type->value];
    }
}
