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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;

/**
 * @internal
 */
final class XlsxFileInterpreter extends AbstractInterpreter
{
    private bool $skipFirstRow;

    private string $sheetName;

    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadSheet = $reader->load($path);

        $spreadSheet->setActiveSheetIndexByName($this->sheetName);

        $data = $spreadSheet->getActiveSheet()->toArray();

        if ($this->skipFirstRow) {
            array_shift($data);
        }

        foreach ($data as $rowData) {
            $this->processImportRow($rowData);
        }
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

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly($this->sheetName);
        $reader->setReadFilter(new PreviewRowsReadFilter(array_filter([$headerRowNumber, $targetRowNumber])));
        $spreadSheet = $reader->load($path);

        $spreadSheet->setActiveSheetIndexByName($this->sheetName);
        $sheet = $spreadSheet->getActiveSheet();

        $headerRow = $this->skipFirstRow ? $this->readRow($sheet, $headerRowNumber, $lastColumnLetter) : null;

        return [$headerRow, $this->readRow($sheet, $targetRowNumber, $lastColumnLetter), $readRecordNumber];
    }

    private function readRow(Worksheet $sheet, int $rowNumber, string $lastColumnLetter): array
    {
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumnLetter);
        $rowData = [];

        for ($column = 1; $column <= $lastColumnIndex; $column++) {
            $rowData[] = $this->getPreviewCellValue($sheet->getCell([$column, $rowNumber]));
        }

        return $rowData;
    }

    /**
     * Formula cells prefer the result cached in the file: only parts of the
     * workbook are loaded for previews, so recalculating formulas with
     * cross-row or cross-sheet references would silently produce wrong
     * values ("#REF!", zeros).
     */
    private function getPreviewCellValue(Cell $cell): mixed
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
