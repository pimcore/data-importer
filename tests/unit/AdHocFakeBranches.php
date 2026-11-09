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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Pimcore\Contracts\ChangeControl\BranchServiceInterface;
use Pimcore\Contracts\ChangeControl\ConflictPolicy;
use Pimcore\Contracts\ChangeControl\DataObjectChange;
use Pimcore\Contracts\ChangeControl\MergeMode;
use Pimcore\Contracts\ChangeControl\MergeStatus;
use Pimcore\Contracts\ChangeControl\ObjectRef;
use Pimcore\Model\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Change Control's branches as the ad hoc import sees them, in memory: what was called, and the merges' answers in
 * the order they come.
 */
final class AdHocFakeBranches implements BranchServiceInterface
{
    public const string BRANCH = '0192f5e0-0000-7000-8000-000000000001';

    /** @var list<string> every call, in order */
    public array $calls = [];

    /** @var list<DataObjectChange> */
    public array $recorded = [];

    /** @var list<ObjectRef> */
    public array $takenOut = [];

    /** @var list<array{MergeMode, ConflictPolicy, string}> mode, policy and the user's name of each merge */
    public array $merges = [];

    /**
     * @param list<MergeStatus|\Throwable> $mergeAnswers what each merge() returns or throws
     * @param list<MergeStatus|null> $statusAnswers what each mergeStatus() returns
     * @param \Throwable|null $recordRefusal what record() throws
     * @param \Throwable|null $discardRefusal what discard() throws
     */
    public function __construct(
        public array $mergeAnswers = [],
        public array $statusAnswers = [],
        public ?\Throwable $recordRefusal = null,
        public ?\Throwable $discardRefusal = null,
    ) {
    }

    public static function branch(): Uuid
    {
        return Uuid::fromString(self::BRANCH);
    }

    public function open(string $name, string $context, UserInterface $by): Uuid
    {
        $this->calls[] = sprintf('open "%s" in %s as %s', $name, $context, $by->getName());

        return self::branch();
    }

    public function record(Uuid $branch, string $context, UserInterface $by, array $changes): void
    {
        $this->calls[] = sprintf('record %d in %s', count($changes), $context);
        if ($this->recordRefusal !== null) {
            throw $this->recordRefusal;
        }
        $this->recorded = [...$this->recorded, ...$changes];
    }

    public function takeOut(Uuid $branch, string $context, UserInterface $by, array $objects): void
    {
        $this->calls[] = 'take out ' . count($objects);
        $this->takenOut = [...$this->takenOut, ...$objects];
    }

    public function merge(
        Uuid $branch,
        string $context,
        UserInterface $by,
        MergeMode $mode,
        ConflictPolicy $conflicts,
    ): MergeStatus {
        $this->calls[] = 'merge';
        $this->merges[] = [$mode, $conflicts, (string) $by->getName()];
        $answer = array_shift($this->mergeAnswers) ?? throw new \LogicException('No merge expected.');
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return $answer;
    }

    public function mergeStatus(Uuid $branch, string $context, UserInterface $by): ?MergeStatus
    {
        $this->calls[] = 'status';

        return array_shift($this->statusAnswers);
    }

    public function discard(Uuid $branch, string $context, UserInterface $by): void
    {
        $this->calls[] = 'discard';
        if ($this->discardRefusal !== null) {
            throw $this->discardRefusal;
        }
    }
}
