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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Apply;

use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\RowReaderInterface;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;

/**
 * Reads the rows of a source file with the file format of a Data Importer configuration, without queueing them, e.g.
 * to check a file before importing it or to pass its rows to PreparedMapping::apply().
 */
final class SourceFileReader
{
    // interpreters log under this configuration name; reading rows logs nothing
    private const CONFIG_NAME = '';

    public function __construct(
        private readonly InterpreterFactory $interpreterFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $interpreterConfig the `interpreterConfig` of a Data Importer configuration
     * @param bool $typedValues XLSX only: stored cell values (int, float, bool, null for empty cells) instead of the
     *                          text an import receives; CSV values are always strings
     *
     * @return iterable<int, array<int|string, mixed>> the rows an import of the file would queue, keyed the same way
     *
     * @throws InvalidConfigurationException if the file format is unknown or cannot read rows
     * @throws InvalidInputException if the file is not valid for the format or cannot be read, or a row is not UTF-8
     *                               encoded or does not match the CSV header row (the message names the row)
     */
    public function readRows(array $interpreterConfig, string $path, bool $typedValues = false): iterable
    {
        $interpreter = $this->interpreterFactory->loadInterpreter(self::CONFIG_NAME, $interpreterConfig, []);
        if (!$interpreter instanceof RowReaderInterface) {
            throw new InvalidConfigurationException(sprintf(
                'File format `%s` cannot read rows without importing them.',
                $interpreterConfig['type'] ?? ''
            ));
        }

        return $interpreter->readRows($path, $typedValues);
    }
}
