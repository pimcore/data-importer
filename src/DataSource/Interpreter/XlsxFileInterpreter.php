<?php

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

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Throwable;

/**
 * @internal
 */
final class XlsxFileInterpreter extends AbstractInterpreter
{
    // Rows per chunk: the sheet XML is parsed again per chunk, so a smaller one costs time without saving much memory.
    private const IMPORT_CHUNK_ROWS = 10000;

    // Cells that get their own warning per import run before the closing summary line.
    private const MAX_UNCACHED_FORMULA_WARNINGS = 50;

    private bool $skipFirstRow;

    private string $sheetName;

    private int $uncachedFormulaCellCount = 0;

    /**
     * @param int $windowedReadCellThreshold cells above which the sheet is read in row windows
     */
    public function __construct(
        DeltaChecker $deltaChecker,
        QueueService $queueService,
        ApplicationLogger $applicationLogger,
        private readonly int $windowedReadCellThreshold,
    ) {
        parent::__construct($deltaChecker, $queueService, $applicationLogger);
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        $worksheetInfo = $this->getWorksheetInfo($path);

        if ($worksheetInfo === null) {
            throw new InvalidConfigurationException(sprintf('Sheet "%s" not found in file.', $this->sheetName));
        }

        $totalRows = $worksheetInfo['totalRows'];
        $lastColumnLetter = $worksheetInfo['lastColumnLetter'];
        $firstDataRow = $this->skipFirstRow ? 2 : 1;
        $this->uncachedFormulaCellCount = 0;

        // a sheet without data rows or without a single cell has no window to read
        if ($totalRows < $firstDataRow || $lastColumnLetter === '') {
            return;
        }

        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumnLetter);
        // two reads rather than one: on 100k rows the windowed read saved 2.9x memory at 4x the interpretation time
        $readInWindows = $totalRows * $lastColumnIndex > $this->windowedReadCellThreshold;
        $windowRows = $readInWindows ? self::IMPORT_CHUNK_ROWS : $totalRows;

        for ($firstRow = $firstDataRow; $firstRow <= $totalRows; $firstRow += $windowRows) {
            $lastRow = min($firstRow + $windowRows - 1, $totalRows);

            $spreadSheet = $this->loadRows($path, $readInWindows ? range($firstRow, $lastRow) : null);
            $sheet = $spreadSheet->getActiveSheet();

            for ($rowNumber = $firstRow; $rowNumber <= $lastRow; $rowNumber++) {
                $this->processImportRow(
                    $this->readImportRow($spreadSheet, $sheet, $rowNumber, $lastColumnLetter, $lastColumnIndex)
                );
            }

            // without this the cyclic references inside PhpSpreadsheet keep the chunk alive
            $spreadSheet->disconnectWorksheets();
            unset($sheet, $spreadSheet);
        }

        $this->logSuppressedUncachedFormulaWarnings();
    }

    public function fileValid(string $path, bool $originalFilename = false): bool
    {
        $reader = IOFactory::createReaderForFile($path);

        return $reader->canRead($path);
    }

    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData
    {
        $previewData = [];
        $columns = [];
        $readRecordNumber = 0;

        $rows = $this->fileValid($path) ? $this->loadPreviewRows($path, $recordNumber) : null;

        if ($rows !== null) {
            [$headerRow, $previewDataRow, $readRecordNumber] = $rows;

            $columns = $this->extractColumns($headerRow);

            foreach ($previewDataRow as $index => $columnData) {
                $previewData[$index] = $columnData;
            }

            if (empty($columns)) {
                $columns = array_keys($previewData);
            }
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);
    }

    /**
     * Reads the header row and the requested data row without loading the whole
     * workbook - large files would otherwise exhaust the memory limit.
     *
     * @return array{0: ?array, 1: array, 2: int}|null
     */
    private function loadPreviewRows(string $path, int $recordNumber): ?array
    {
        $worksheetInfo = $this->getWorksheetInfo($path);

        if ($worksheetInfo === null) {
            throw new InvalidConfigurationException(sprintf('Sheet "%s" not found in file.', $this->sheetName));
        }

        $totalRows = $worksheetInfo['totalRows'];
        $lastColumnLetter = $worksheetInfo['lastColumnLetter'];
        $headerRowNumber = $this->skipFirstRow ? 1 : 0;
        $totalDataRows = max(0, $totalRows - $headerRowNumber);

        if ($totalDataRows < 1) {
            return null;
        }

        $targetRowNumber = $headerRowNumber + 1 + $recordNumber;
        $readRecordNumber = $recordNumber;
        if ($recordNumber < 0 || $targetRowNumber > $totalRows) {
            // fall back to the last data row, mirroring the previous out-of-range behavior
            $targetRowNumber = $totalRows;
            $readRecordNumber = $totalDataRows - 1;
        }

        $sheet = $this->loadRows($path, array_filter([$headerRowNumber, $targetRowNumber]))->getActiveSheet();

        $headerRow = $this->skipFirstRow ? $this->readRow($sheet, $headerRowNumber, $lastColumnLetter) : null;

        return [$headerRow, $this->readRow($sheet, $targetRowNumber, $lastColumnLetter), $readRecordNumber];
    }

    /**
     * @param int[]|null $rows 1-based row numbers to read, null for every row of the sheet
     */
    private function loadRows(string $path, ?array $rows): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly($this->sheetName);

        if ($rows !== null) {
            $reader->setReadFilter(new RowsReadFilter($rows));
        }

        $spreadSheet = $reader->load($path);

        $spreadSheet->setActiveSheetIndexByName($this->sheetName);

        return $spreadSheet;
    }

    private function readImportRow(
        Spreadsheet $spreadSheet,
        Worksheet $sheet,
        int $rowNumber,
        string $lastColumnLetter,
        int $lastColumnIndex
    ): array {
        // only the cells the row has: probing every column position costs two lookups per empty cell
        $cells = new RowCellIterator($sheet, $rowNumber, 'A', $lastColumnLetter, true);
        $rowData = array_fill(0, $lastColumnIndex, null);

        foreach ($cells as $cell) {
            $rowData[$cells->getCurrentColumnIndex() - 1] = $this->getImportCellValue($spreadSheet, $cell);
        }

        return $rowData;
    }

    /**
     * Mirrors what the whole-workbook Worksheet::toArray() produced: an empty cell stays
     * null, every other value goes through the number format of its cell. A formula that
     * cannot be resolved stays null as well, rather than becoming the empty string the
     * number format would make of it.
     */
    private function getImportCellValue(Spreadsheet $spreadSheet, Cell $cell): mixed
    {
        if ($cell->getValue() === null) {
            return null;
        }

        $value = $this->getCellValue($cell);

        $this->warnAboutUncachedFormula($cell, $value === null);

        if ($value === null) {
            return null;
        }

        $formatCode = $spreadSheet->getCellXfByIndex($cell->getXfIndex())->getNumberFormat()->getFormatCode();

        return NumberFormat::toFormattedString($value, $formatCode ?? NumberFormat::FORMAT_GENERAL);
    }

    /**
     * @param bool $importedEmpty the formula could not be resolved at all, so the cell was imported empty
     */
    private function warnAboutUncachedFormula(Cell $cell, bool $importedEmpty): void
    {
        if ($cell->getDataType() !== DataType::TYPE_FORMULA || $cell->getOldCalculatedValue() !== null) {
            return;
        }

        $this->uncachedFormulaCellCount++;

        if ($this->uncachedFormulaCellCount > self::MAX_UNCACHED_FORMULA_WARNINGS) {
            return;
        }

        $message = $importedEmpty
            ? 'Formula in cell "%s!%s" has no result cached in the file and could not be calculated, '
                . 'so the cell was imported empty and the import continued.'
            : 'Formula in cell "%s!%s" has no result cached in the file and was calculated on the loaded rows only, '
                . 'so the imported value can differ from the one the spreadsheet application shows.';

        $this->logUncachedFormulaWarning(sprintf($message, $this->sheetName, $cell->getCoordinate()));
    }

    private function logSuppressedUncachedFormulaWarnings(): void
    {
        if ($this->uncachedFormulaCellCount <= self::MAX_UNCACHED_FORMULA_WARNINGS) {
            return;
        }

        $this->logUncachedFormulaWarning(sprintf(
            '%d further cells carry a formula without a cached result and were calculated on the loaded rows only '
            . 'or imported empty, only the first %d of them are listed above.',
            $this->uncachedFormulaCellCount - self::MAX_UNCACHED_FORMULA_WARNINGS,
            self::MAX_UNCACHED_FORMULA_WARNINGS
        ));
    }

    private function logUncachedFormulaWarning(string $message): void
    {
        $this->applicationLogger->warning($message, [
            'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
        ]);
    }

    private function readRow(Worksheet $sheet, int $rowNumber, string $lastColumnLetter): array
    {
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumnLetter);
        $rowData = [];

        for ($column = 1; $column <= $lastColumnIndex; $column++) {
            $rowData[] = $this->getCellValue($sheet->getCell([$column, $rowNumber]));
        }

        return $rowData;
    }

    /**
     * Formula cells prefer the result cached in the file. The preview always, and the import
     * above its cell threshold, load only parts of the workbook, so recalculating formulas
     * with cross-row or cross-sheet references would silently produce wrong values
     * ("#REF!", zeros). Both paths apply this rule, so neither can drift from the other.
     *
     * Calculating can also throw rather than return an error string - an unparseable formula, or
     * one the calculation engine does not implement. Throwable rather than Exception, and null
     * rather than a re-throw: a single such cell must not abort an import of 100,000 rows, and
     * windowing makes formulas reaching outside the loaded rows routine. The import names every
     * such cell in the log, so the empty cell is never silent.
     */
    private function getCellValue(Cell $cell): mixed
    {
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            $cachedValue = $cell->getOldCalculatedValue();
            if ($cachedValue !== null) {
                return $cachedValue;
            }
        }

        try {
            $value = $cell->getCalculatedValue();
        } catch (Throwable) {
            return null;
        }

        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        return $value;
    }

    private function extractColumns(?array $headerRow): array
    {
        $columns = [];

        foreach ($headerRow ?? [] as $index => $columnHeader) {
            $columns[$index] = trim((string)$columnHeader) . " [$index]";
        }

        return $columns;
    }

    /**
     * Reads the worksheet dimensions without loading any cells.
     * Returns null when the configured sheet does not exist in the file.
     *
     * @return array{totalRows: int, lastColumnLetter: string}|null
     */
    private function getWorksheetInfo(string $path): ?array
    {
        $reader = IOFactory::createReaderForFile($path);

        foreach ($reader->listWorksheetInfo($path) as $worksheetInfo) {
            if ($worksheetInfo['worksheetName'] === $this->sheetName) {
                return [
                    'totalRows' => $worksheetInfo['totalRows'],
                    'lastColumnLetter' => $worksheetInfo['lastColumnLetter'],
                ];
            }
        }

        return null;
    }

    public function setSettings(array $settings): void
    {
        $this->skipFirstRow = $settings['skipFirstRow'] ?? false;
        $this->sheetName = $settings['sheetName'] ?? 'Sheet1';
    }
}
