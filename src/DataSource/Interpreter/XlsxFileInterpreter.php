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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;

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

        for ($firstRow = $firstDataRow; $firstRow <= $totalRows; $firstRow += self::IMPORT_CHUNK_ROWS) {
            $lastRow = min($firstRow + self::IMPORT_CHUNK_ROWS - 1, $totalRows);

            $spreadSheet = $this->loadRows($path, range($firstRow, $lastRow));
            $sheet = $spreadSheet->getActiveSheet();

            for ($rowNumber = $firstRow; $rowNumber <= $lastRow; $rowNumber++) {
                $this->processImportRow($this->readImportRow($spreadSheet, $sheet, $rowNumber, $lastColumnIndex));
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
     * @param int[] $rows 1-based row numbers to read
     */
    private function loadRows(string $path, array $rows): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly($this->sheetName);
        $reader->setReadFilter(new RowsReadFilter($rows));
        $spreadSheet = $reader->load($path);

        $spreadSheet->setActiveSheetIndexByName($this->sheetName);

        return $spreadSheet;
    }

    private function readImportRow(
        Spreadsheet $spreadSheet,
        Worksheet $sheet,
        int $rowNumber,
        int $lastColumnIndex
    ): array {
        $rowData = [];

        for ($column = 1; $column <= $lastColumnIndex; $column++) {
            $coordinate = [$column, $rowNumber];

            if (!$sheet->cellExists($coordinate)) {
                $rowData[] = null;

                continue;
            }

            $cell = $sheet->getCell($coordinate);
            $this->warnAboutUncachedFormula($cell);
            $rowData[] = $this->getImportCellValue($spreadSheet, $cell);
        }

        return $rowData;
    }

    /**
     * Mirrors what the whole-workbook Worksheet::toArray() produced: an empty cell stays
     * null, every other value goes through the number format of its cell.
     */
    private function getImportCellValue(Spreadsheet $spreadSheet, Cell $cell): mixed
    {
        if ($cell->getValue() === null) {
            return null;
        }

        $formatCode = $spreadSheet->getCellXfByIndex($cell->getXfIndex())->getNumberFormat()->getFormatCode();

        return NumberFormat::toFormattedString($this->getCellValue($cell), $formatCode ?? NumberFormat::FORMAT_GENERAL);
    }

    private function warnAboutUncachedFormula(Cell $cell): void
    {
        if ($cell->getDataType() !== DataType::TYPE_FORMULA || $cell->getOldCalculatedValue() !== null) {
            return;
        }

        $this->uncachedFormulaCellCount++;

        if ($this->uncachedFormulaCellCount > self::MAX_UNCACHED_FORMULA_WARNINGS) {
            return;
        }

        $this->logUncachedFormulaWarning(sprintf(
            'Formula in cell "%s!%s" has no result cached in the file and was calculated on the loaded rows only, '
            . 'so the imported value can differ from the one the spreadsheet application shows.',
            $this->sheetName,
            $cell->getCoordinate()
        ));
    }

    private function logSuppressedUncachedFormulaWarnings(): void
    {
        if ($this->uncachedFormulaCellCount <= self::MAX_UNCACHED_FORMULA_WARNINGS) {
            return;
        }

        $this->logUncachedFormulaWarning(sprintf(
            '%d further cells with a formula without a cached result were calculated on the loaded rows only, '
            . 'only the first %d of them are listed above.',
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
     * Formula cells prefer the result cached in the file: both the preview and the
     * import load only parts of the workbook, so recalculating formulas with
     * cross-row or cross-sheet references would silently produce wrong
     * values ("#REF!", zeros).
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
        } catch (\Throwable) {
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
