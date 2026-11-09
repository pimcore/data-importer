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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RecordedRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWrite;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolverInterface;
use Pimcore\Contracts\ChangeControl\BranchServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\Uid\Uuid;

/**
 * Writes a run's rows into a Change Control branch, one row per call, so that a row the branch refuses fails alone.
 * Nothing reaches the objects before the branch is merged.
 *
 * @internal
 */
final readonly class BranchRowWriter implements RowWriterInterface
{
    public function __construct(
        private BranchServiceInterface $branches,
        private ObjectChanges $changes,
        private ConcreteObjectResolverInterface $objects,
        private RunStoreInterface $runs,
        private RunMessages $messages,
    ) {
    }

    public static function context(RunRecord $run): string
    {
        return 'data-importer:ad-hoc-import:' . $run->fileId;
    }

    public static function branchOf(RunRecord $run): Uuid
    {
        return Uuid::fromString((string) $run->branch);
    }

    public function open(RunRecord $run, User $user): RunRecord
    {
        $branch = $this->branches->open($this->messages->branchName($run, $user), self::context($run), $user);

        return $run->withBranch($branch->toRfc4122());
    }

    public function write(RowWrite $write): void
    {
        $run = $write->run;
        if ($run?->branch === null) {
            throw new \LogicException('A row is written into a branch only within a run that opened one.');
        }

        $object = $write->object;
        $token = RecordedRow::tokenOf($write->rowNumber);
        $before = $write->isNew
            ? new ($object::class)()
            : $this->objects->getById((int) $object->getId(), ['force' => true])
                ?? throw new \RuntimeException(sprintf('Object %d was deleted meanwhile.', (int) $object->getId()));
        $change = $this->changes->changeOf($object, $before, $write->isNew ? $token : null);
        $this->branches->record(self::branchOf($run), self::context($run), $write->user, [$change]);

        $this->runs->addRecorded($run, new RecordedRow(
            $write->rowNumber,
            $write->isNew ? null : (int) $object->getId(),
            $write->isNew ? $token : null,
            $write->matchValue,
            $write->isNew ? 'create' : 'update',
            (string) $object->getKey(),
            $write->folder,
            $write->values,
            $write->row
        ));
    }

    public function close(RunRecord $run): array
    {
        // what the merge finds out comes later, from the merge
        return [];
    }
}
