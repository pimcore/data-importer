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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunRow;

/**
 * A file checked row by row, and what the check went by.
 *
 * @internal
 */
final readonly class CheckedFile
{
    /**
     * @param list<DryRunRow> $results in the order of the file
     */
    public function __construct(
        public ImportPlan $plan,
        public array $results,
    ) {
    }
}
