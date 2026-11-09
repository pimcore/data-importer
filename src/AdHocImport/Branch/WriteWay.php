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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolverInterface;
use Pimcore\Contracts\ChangeControl\BranchServiceInterface;

/**
 * Where a run's rows are written: into a branch when Change Control is installed, else straight to the objects.
 *
 * @internal
 */
final readonly class WriteWay
{
    public function __construct(
        private DirectRowWriter $direct,
        private ObjectChanges $changes,
        private ConcreteObjectResolverInterface $objects,
        private RunStoreInterface $runs,
        private RunMessages $messages,
        private ?BranchServiceInterface $branches = null,
    ) {
    }

    public function writer(): RowWriterInterface
    {
        return $this->branches === null
            ? $this->direct
            : new BranchRowWriter($this->branches, $this->changes, $this->objects, $this->runs, $this->messages);
    }
}
