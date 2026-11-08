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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Matching;

/**
 * Resolves the rows of one file to their objects.
 *
 * @internal
 */
interface RowMatcherInterface
{
    /**
     * @param array<int|string, mixed> $row import data row, keyed like the rows of the Data Importer
     * @param int $rowNumber the row's number in the file, reported back by later rows that refer to it
     */
    public function match(array $row, int $rowNumber): RowMatch;
}
