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
use Pimcore\Contracts\ChangeControl\FieldKind;
use Pimcore\Contracts\ChangeControl\MergeMode;
use Pimcore\Contracts\ChangeControl\MergeProblem;
use Pimcore\Contracts\ChangeControl\MergeProblemCode;
use Pimcore\Contracts\ChangeControl\MergeStatus;
use Pimcore\Contracts\ChangeControl\ObjectRef;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Change Control as far as the import needs it, without its bundle: the changes are kept and a merge creates the
 * objects for real. New objects only: saving one again needs a search index the test environment does not have.
 */
final class AdHocApplyingBranches implements BranchServiceInterface
{
    /** @var array<string, array<string, DataObjectChange>> by branch and token: a branch holds only its own run */
    private array $changes = [];

    /** @var list<array{MergeMode, ConflictPolicy, string}> mode, policy and the user's name of each merge */
    public array $merges = [];

    /** @var list<string> the tokens taken out */
    public array $takenOut = [];

    public int $discards = 0;

    /**
     * @param list<string> $refusing the keys of the objects a merge refuses before it writes anything
     * @param list<string> $failing the keys of the objects a merge cannot write
     */
    public function __construct(
        public array $refusing = [],
        private readonly array $failing = [],
    ) {
    }

    public function open(string $name, string $context, UserInterface $by): Uuid
    {
        return Uuid::v7();
    }

    public function record(Uuid $branch, string $context, UserInterface $by, array $changes): void
    {
        foreach ($changes as $change) {
            $token = $change->create?->token ?? throw new \LogicException('This fake creates objects only.');
            $this->changes[(string) $branch][$token] = $change;
        }
    }

    public function takeOut(Uuid $branch, string $context, UserInterface $by, array $objects): void
    {
        foreach ($objects as $object) {
            $this->takenOut[] = (string) $object->token;
            unset($this->changes[(string) $branch][(string) $object->token]);
        }
    }

    public function merge(
        Uuid $branch,
        string $context,
        UserInterface $by,
        MergeMode $mode,
        ConflictPolicy $conflicts,
    ): MergeStatus {
        $this->merges[] = [$mode, $conflicts, (string) $by->getName()];

        $changes = $this->changes[(string) $branch] ?? [];
        $refused = $this->problems($changes, $this->refusing, MergeProblemCode::InvalidValue);
        if ($refused !== []) {
            return MergeStatus::refused($branch, count($changes), $refused);
        }

        $problems = $this->problems($changes, $this->failing, MergeProblemCode::WriteFailed);
        $written = [];
        foreach ($changes as $token => $change) {
            if (!in_array($change->create?->key, $this->failing, true)) {
                $written[] = ObjectRef::created($token, $this->create($change, $by));
            }
        }

        return $problems === []
            ? MergeStatus::merged($branch, $written)
            : MergeStatus::partial($branch, count($changes), $written, $problems);
    }

    public function mergeStatus(Uuid $branch, string $context, UserInterface $by): ?MergeStatus
    {
        throw new \LogicException('A merge here ends at once: there is nothing to follow.');
    }

    public function discard(Uuid $branch, string $context, UserInterface $by): void
    {
        ++$this->discards;
        unset($this->changes[(string) $branch]);
    }

    /**
     * @param array<string, DataObjectChange> $changes by token
     * @param list<string> $keys
     *
     * @return list<MergeProblem>
     */
    private function problems(array $changes, array $keys, MergeProblemCode $code): array
    {
        $problems = [];
        foreach ($changes as $token => $change) {
            if (in_array($change->create?->key, $keys, true)) {
                $problems[] = new MergeProblem(
                    ObjectRef::token($token),
                    null,
                    'the reason of Change Control',
                    code: $code
                );
            }
        }

        return $problems;
    }

    private function create(DataObjectChange $change, UserInterface $by): int
    {
        $new = $change->create ?? throw new \LogicException('This fake creates objects only.');
        $class = '\\Pimcore\\Model\\DataObject\\' . $new->className;
        /** @var Concrete $object */
        $object = new $class();
        $object->setParentId((int) $new->parent);
        $object->setKey($new->key);
        $object->setPublished(false);
        $object->setUserOwner((int) $by->getId());
        $object->setUserModification((int) $by->getId());
        foreach ($change->values() as $value) {
            $value->kind === FieldKind::Localized
                ? $object->set($value->field, $value->value, $value->language)
                : $object->set($value->field, $value->value);
        }
        $object->save();

        return (int) $object->getId();
    }
}
