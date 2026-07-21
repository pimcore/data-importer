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

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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
        $totalRows = $this->getTotalRows($path);
        $headerRowNumber = $this->skipFirstRow ? 1 : 0;
        $totalDataRows = max(0, $totalRows - $headerRowNumber);

        if ($totalDataRows < 1) {
            return null;
        }

        $targetRowNumber = $headerRowNumber + 1 + $recordNumber;
        $readRecordNumber = $recordNumber;
        if ($targetRowNumber > $totalRows) {
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

        $headerRow = $this->skipFirstRow ? $this->readRow($sheet, $headerRowNumber) : null;

        return [$headerRow, $this->readRow($sheet, $targetRowNumber), $readRecordNumber];
    }

    private function readRow(Worksheet $sheet, int $rowNumber): array
    {
        $range = 'A' . $rowNumber . ':' . $sheet->getHighestColumn() . $rowNumber;

        return $sheet->rangeToArray($range)[0];
    }

    private function extractColumns(?array $headerRow): array
    {
        $columns = [];

        foreach ($headerRow ?? [] as $index => $columnHeader) {
            $columns[$index] = trim((string)$columnHeader) . " [$index]";
        }

        return $columns;
    }

    private function getTotalRows(string $path): int
    {
        $reader = IOFactory::createReaderForFile($path);

        foreach ($reader->listWorksheetInfo($path) as $worksheetInfo) {
            if (($worksheetInfo['worksheetName'] ?? null) === $this->sheetName) {
                return (int)($worksheetInfo['totalRows'] ?? 0);
            }
        }

        return 0;
    }

    public function setSettings(array $settings): void
    {
        $this->skipFirstRow = $settings['skipFirstRow'] ?? false;
        $this->sheetName = $settings['sheetName'] ?? 'Sheet1';
    }
}
