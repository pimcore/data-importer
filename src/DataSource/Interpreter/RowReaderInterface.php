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

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;

/**
 * A file format whose rows can be read without importing them. Implemented by the CSV and XLSX file formats.
 *
 * @see SourceFileReader::readRows()
 */
interface RowReaderInterface
{
    /**
     * Returns the rows an import of the file would queue, keyed the same way, without queueing them. Unlike an import
     * it skips no unchanged rows (delta check), cleans up no elements and writes nothing to the application logger.
     *
     * @param bool $typedValues return the stored value of each cell instead of the text an import receives, where the
     *                          format has value types
     *
     * @return iterable<int, array<int|string, mixed>>
     *
     * @throws InvalidInputException if the file is not valid for the format or cannot be read, or a row is not UTF-8
     *                               encoded or does not match the CSV header row (the message names the row);
     *                               thrown by this call or while the rows are iterated
     */
    public function readRows(string $path, bool $typedValues = false): iterable;
}
