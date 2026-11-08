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
 * The queue a run's rows wait in, and how the workers are told about them.
 *
 * @internal
 */
interface RunQueueInterface
{
    /**
     * @param array<int, list<int>> $rows per position in the file, the writers the row goes without
     */
    public function enqueue(RunRecord $run, array $rows): void;
}
