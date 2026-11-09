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
    // its rows are being written
    public const string RUNNING = 'running';

    // its branch is being checked and merged
    public const string MERGING = 'merging';

    // rows cannot be saved, and nothing is merged: the user chooses how to go on
    public const string DECISION = 'decision';

    public const string DONE = 'done';

    // a queue item's config name is at most 80 characters, and a run has no configuration to name
    private const string QUEUE_PREFIX = 'ad-hoc-import:';

    /**
     * @param array<string, mixed> $parameters what the dry run was asked with, by name
     * @param array{configuration: array<mixed>, mapping: array<int|string, mixed>}|null $configuration the
     *     configuration as it was when the run started, so a later change does not touch the run
     * @param int $total the rows the run imports
     * @param int $skipped the rows the preview's choice left out
     * @param string|null $branch the branch the rows are written into, when Change Control takes the writes
     * @param int $mergeTotal the objects the merge covers, while it runs
     * @param int $mergeDone the objects the merge has written so far
     * @param int|null $waitingSince when the merge last got on, or since when the run waits for a decision
     * @param string|null $notice what went wrong that belongs to no row, in the user's words
     * @param list<string> $columns the file's columns, for the rows a later step finds it cannot save
     * @param int|null $checkedAt when it was last looked how the merge goes
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
        public string $targetFolder = '',
        public ?string $branch = null,
        public int $mergeTotal = 0,
        public int $mergeDone = 0,
        public ?int $waitingSince = null,
        public ?string $notice = null,
        public array $columns = [],
        public ?int $checkedAt = null,
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

    /**
     * Rows are being written, or the merge runs: the file is in use.
     */
    public function isBusy(): bool
    {
        return $this->state === self::RUNNING || $this->state === self::MERGING;
    }

    public function isWaitingForDecision(): bool
    {
        return $this->state === self::DECISION;
    }

    public function throughBranch(): bool
    {
        return $this->branch !== null;
    }

    public function withBranch(string $branch): self
    {
        return self::fromArray([...$this->toArray(), 'branch' => $branch]);
    }

    /**
     * The merge goes on: the time only moves when it wrote more, so a merge nobody drives shows as stalled.
     */
    public function merging(int $total, int $done, int $time): self
    {
        $moved = $this->state !== self::MERGING || $done !== $this->mergeDone || $this->waitingSince === null;

        return self::fromArray([
            ...$this->toArray(),
            'state' => self::MERGING,
            'mergeTotal' => $total,
            'mergeDone' => $done,
            'waitingSince' => $moved ? $time : $this->waitingSince,
            'checkedAt' => $time,
        ]);
    }

    public function stalled(int $time, int $limit): bool
    {
        return $this->state === self::MERGING && $this->waitingSince !== null && $time - $this->waitingSince >= $limit;
    }

    public function waitingForDecision(int $time): self
    {
        return self::fromArray([...$this->toArray(), 'state' => self::DECISION, 'waitingSince' => $time]);
    }

    /**
     * @param list<string> $columns
     */
    public function withColumns(array $columns): self
    {
        return self::fromArray([...$this->toArray(), 'columns' => $columns]);
    }

    public function withNotice(?string $notice): self
    {
        return self::fromArray([...$this->toArray(), 'notice' => $notice]);
    }

    public function isComplete(): bool
    {
        return $this->processed() >= $this->total;
    }

    public function counted(RowOutcome $outcome): self
    {
        return self::fromArray([...$this->toArray(), $outcome->result => $this->{$outcome->result} + 1]);
    }

    /**
     * A row counted as written that only the end of the run found to have failed: it moves, so the counts add up.
     */
    public function failedAtClose(RowFailure $failure): self
    {
        return $this->failedAfterWrite($failure->action);
    }

    /**
     * A row counted as written that the merge did not write: it moves, so the counts add up.
     */
    public function failedAfterWrite(?string $action): self
    {
        $written = $action === 'create' ? RowOutcome::CREATED : RowOutcome::UPDATED;

        return self::fromArray([
            ...$this->toArray(),
            $written => max(0, $this->{$written} - 1),
            RowOutcome::FAILED => $this->failed + 1,
        ]);
    }

    /**
     * Rows written into a branch that was never merged count as not saved.
     */
    public function nothingSaved(): self
    {
        return self::fromArray([
            ...$this->toArray(),
            RowOutcome::UPDATED => 0,
            RowOutcome::CREATED => 0,
            RowOutcome::FAILED => $this->processed(),
        ]);
    }

    public function finished(int $time): self
    {
        return self::fromArray([
            ...$this->toArray(),
            'state' => self::DONE,
            'finishedAt' => $time,
            'waitingSince' => null,
        ]);
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
