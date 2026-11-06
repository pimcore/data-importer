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

use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;

/**
 * Reads a file the way its import will, so a file refused here is one the import could not take either. Of a workbook
 * it loads only the chosen sheet: another sheet's size is no reason to refuse this one.
 *
 * @internal
 */
final readonly class FileInspector
{
    public const int MAX_ROWS = 5000;

    private const int FIRST_ROWS = 3;

    public function __construct(
        private SourceFileReader $sourceFileReader,
        private InterpreterFactory $interpreterFactory,
        private CsvDelimiterDetector $csvDelimiterDetector,
    ) {
    }

    /**
     * Whether the file is of the given type at all, whatever its settings.
     */
    public function isReadable(string $path, FileType $type): bool
    {
        return match ($type) {
            FileType::Csv => $this->interpreterFactory
                ->loadInterpreter('', ['type' => FileType::Csv->value, 'settings' => []], [])
                ->fileValid($path),
            // not the interpreter's check: it also accepts what another reader can read, like plain text
            FileType::Xlsx => (new Xlsx())->canRead($path),
        };
    }

    public function inspect(string $path, FileType $type, SettingsInput $input): FileInspection
    {
        return match ($type) {
            FileType::Csv => $this->inspectCsv($path, $input),
            FileType::Xlsx => $this->inspectXlsx($path, $input),
        };
    }

    private function inspectCsv(string $path, SettingsInput $input): FileInspection
    {
        $detectedDelimiter = $this->csvDelimiterDetector->detect($path);
        $settings = [
            'delimiter' => $input->delimiter ?? $detectedDelimiter,
            'enclosure' => $input->enclosure ?? '"',
            'escape' => $input->escape ?? '\\',
            'hasHeaderRow' => $input->hasHeaderRow,
        ];
        $refused = new FileInspection($settings, $detectedDelimiter, refusal: RefusalReason::Unreadable);

        $format = ['delimiter' => $settings['delimiter'], 'enclosure' => $settings['enclosure'], 'escape' => $settings['escape']];

        try {
            $columns = null;
            if ($input->hasHeaderRow) {
                $columns = $this->csvHeaderColumns($path, $format);
                if ($columns === null) {
                    return $refused;
                }
            }

            $rowCount = 0;
            $firstRows = [];
            foreach ($this->sourceFileReader->readRows(
                ['type' => FileType::Csv->value, 'settings' => $format + ['skipFirstRow' => $input->hasHeaderRow, 'saveHeaderName' => $input->hasHeaderRow]],
                $path
            ) as $row) {
                $columns ??= $this->columnsByPosition($row);
                if ($rowCount < self::FIRST_ROWS) {
                    $firstRows[] = $this->rowValues($row, $columns);
                }
                ++$rowCount;
            }
        } catch (InvalidInputException|\ValueError) {
            // \ValueError: fgetcsv() refuses a delimiter, enclosure or escape of more than one byte
            return $refused;
        }

        if ($rowCount > self::MAX_ROWS) {
            return new FileInspection($settings, $detectedDelimiter, rowCount: $rowCount, refusal: RefusalReason::TooManyRows);
        }

        return new FileInspection($settings, $detectedDelimiter, [], $rowCount, $columns ?? [], $firstRows);
    }

    /**
     * @param array{delimiter: string, enclosure: string, escape: string} $format
     *
     * @return array<int, array{dataIndex: string, label: string}>|null null if two columns share a name: an import
     *                                                                  keeps only one of them
     */
    private function csvHeaderColumns(string $path, array $format): ?array
    {
        $names = [];
        foreach ($this->sourceFileReader->readRows(['type' => FileType::Csv->value, 'settings' => $format], $path) as $row) {
            $names = array_values(array_filter(
                $row,
                static fn (?string $name): bool => $name !== null && $name !== ''
            ));

            break;
        }

        if (count($names) !== count(array_unique($names))) {
            return null;
        }

        return array_map(
            static fn (string $name): array => ['dataIndex' => $name, 'label' => trim($name)],
            $names
        );
    }

    private function inspectXlsx(string $path, SettingsInput $input): FileInspection
    {
        try {
            $worksheets = $this->withWarningsAsErrors(static fn (): array => (new Xlsx())->listWorksheetInfo($path));
        } catch (SpreadsheetException|\ErrorException) {
            return new FileInspection(['hasHeaderRow' => $input->hasHeaderRow], refusal: RefusalReason::Unreadable);
        }

        $sheets = array_column($worksheets, 'worksheetName');
        $sheetName = $input->sheetName ?? $sheets[0] ?? '';
        $settings = ['hasHeaderRow' => $input->hasHeaderRow, 'sheetName' => $sheetName];

        $worksheet = array_values(array_filter(
            $worksheets,
            static fn (array $info): bool => $info['worksheetName'] === $sheetName
        ))[0] ?? null;
        if ($worksheet === null) {
            return new FileInspection($settings, sheets: $sheets, refusal: RefusalReason::Unreadable);
        }

        try {
            // decided before the rows are read: this limit keeps reading them affordable. The sheet's last row may only
            // carry formatting, so a sheet that seems too long is counted
            $headerRows = $input->hasHeaderRow ? 1 : 0;
            if ($worksheet['totalRows'] - $headerRows > self::MAX_ROWS) {
                $lastRow = $this->withWarningsAsErrors(fn (): int => $this->lastRowWithCells($path, $sheetName));
                $rowCount = max(0, $lastRow - $headerRows);
                if ($rowCount > self::MAX_ROWS) {
                    return new FileInspection($settings, sheets: $sheets, rowCount: $rowCount, refusal: RefusalReason::TooManyRows);
                }
            }

            $rows = $this->withWarningsAsErrors(fn (): array => [...$this->sourceFileReader->readRows(
                ['type' => FileType::Xlsx->value, 'settings' => ['skipFirstRow' => false, 'sheetName' => $sheetName, 'onlyChosenSheet' => true]],
                $path
            )]);
        } catch (SpreadsheetException|InvalidInputException|\ErrorException) {
            return new FileInspection($settings, sheets: $sheets, refusal: RefusalReason::Unreadable);
        }

        $columns = $input->hasHeaderRow ? $this->xlsxHeaderColumns(array_shift($rows) ?? []) : $this->columnsByPosition($rows[0] ?? []);

        return new FileInspection(
            $settings,
            sheets: $sheets,
            rowCount: count($rows),
            columns: $columns,
            firstRows: array_map(
                fn (array $row): array => $this->rowValues($row, $columns),
                array_slice($rows, 0, self::FIRST_ROWS)
            )
        );
    }

    /**
     * The reader may tell a broken part of a workbook only by a PHP warning, and goes on without what it could not read.
     *
     * @template T
     *
     * @param callable(): T $read
     *
     * @return T
     *
     * @throws \ErrorException|SpreadsheetException|InvalidInputException
     */
    private function withWarningsAsErrors(callable $read): mixed
    {
        set_error_handler(
            static function (int $level, string $message, string $file, int $line): bool {
                if ((error_reporting() & $level) === 0) {
                    return false;
                }

                throw new \ErrorException($message, 0, $level, $file, $line);
            },
            E_WARNING
        );

        try {
            return $read();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The last row an import receives, without keeping the cells: a row without cells does not reach it.
     */
    private function lastRowWithCells(string $path, string $sheetName): int
    {
        $filter = new LastRowReadFilter();

        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly($sheetName);
        $reader->setReadFilter($filter);
        $reader->load($path);

        return $filter->lastRow();
    }

    /**
     * @param array<int, mixed> $header
     *
     * @return array<int, array{dataIndex: string, label: string}> an unnamed column is left out; XLSX rows stay keyed by position
     */
    private function xlsxHeaderColumns(array $header): array
    {
        $columns = [];
        foreach ($header as $position => $name) {
            $label = trim((string)$name);
            if ($label !== '') {
                $columns[] = ['dataIndex' => (string)$position, 'label' => $label];
            }
        }

        return $columns;
    }

    /**
     * @param array<int|string, mixed> $row
     *
     * @return array<int, array{dataIndex: string, label: string}>
     */
    private function columnsByPosition(array $row): array
    {
        return array_map(
            static fn (int|string $position): array => ['dataIndex' => (string)$position, 'label' => (string)$position],
            array_keys($row)
        );
    }

    /**
     * @param array<int|string, mixed> $row
     * @param array<int, array{dataIndex: string, label: string}> $columns
     *
     * @return array<int, string|null>
     */
    private function rowValues(array $row, array $columns): array
    {
        return array_map(
            static function (array $column) use ($row): ?string {
                $value = $row[$column['dataIndex']] ?? null;

                return $value === null ? null : (string)$value;
            },
            $columns
        );
    }
}
