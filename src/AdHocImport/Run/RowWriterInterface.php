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
 * Where a run's rows are written: straight to the objects, or into a branch that is merged at the end, so open() and
 * close() frame the writes. Rights, the mapping and PreSaveEvent come before this seam; PostSaveEvent comes behind it,
 * when the object is really saved.
 *
 * @internal
 */
interface RowWriterInterface
{
    /**
     * Before the first row.
     */
    public function open(RunRecord $run): void;

    /**
     * Writes one row.
     *
     * @throws \Throwable what kept the row from being written
     */
    public function write(RowWrite $write): void;

    /**
     * After the last row.
     *
     * @return list<RowFailure> the rows only the end of the run found out about
     */
    public function close(RunRecord $run): array;
}
