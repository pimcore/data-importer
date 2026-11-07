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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

use Pimcore\Model\DataObject\Concrete;

/**
 * Writes one field of the objects of a file, in memory.
 *
 * @internal
 */
interface FieldWriter
{
    public function field(): ImportField;

    /**
     * @param int $index the row's position among the file's rows
     * @param array<int|string, mixed> $row
     *
     * @return ValueError|null why the row's value cannot go into the field; the field is then left as it was
     */
    public function write(Concrete $object, int $index, array $row): ?ValueError;

    /**
     * The value the file gives the field, as the file has it.
     *
     * @param array<int|string, mixed> $row
     */
    public function fileValue(array $row): ?string;
}
