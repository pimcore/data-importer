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
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;

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

    /**
     * @return array<int, list<int>> per position in the file of the rows to import, the writers it goes without
     */
    public function importedRows(string $errorMode): array
    {
        $imported = [];
        foreach ($this->results as $index => $result) {
            if ($errorMode === AdHocImportDryRunServiceInterface::SKIP_ROWS) {
                if (!$result->hasErrors()) {
                    $imported[$index] = [];
                }
            } elseif ($result->importsWithoutInvalidValues()) {
                $imported[$index] = $result->leftOut;
            }
        }

        return $imported;
    }
}
