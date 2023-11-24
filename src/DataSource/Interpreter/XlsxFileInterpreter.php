<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use Carbon\Carbon;
use Doctrine\DBAL\Exception;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Options as CSVOptions;
use OpenSpout\Writer\CSV\Writer as CSVWriter;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Db;

class XlsxFileInterpreter extends AbstractInterpreter
{
    /**
     * @var bool
     */
    protected bool $skipFirstRow;

    /**
     * @var string
     */
    protected string $sheetName;

    /**
     * @var bool
     */
    protected bool $bulkQueue;

    /**
     * @throws WriterNotOpenedException
     * @throws IOException
     * @throws Exception
     */
    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        $data = $this->getExcelData($path, $this->sheetName);

        if ($this->skipFirstRow) {
            array_shift($data);
        }

        if ($this->bulkQueue) {
            $this->bulkLoadData($data);

            return;
        }

        foreach ($data as $rowData) {
            $this->processImportRow($rowData);
        }
    }

    public function fileValid(string $path, bool $originalFilename = false): bool
    {
        //if we can't open the reader, then the file is not valid
        try {
            $reader = new XlsxReader();
            $reader->open($path);
            $reader->close();
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData
    {
        $previewData = [];
        $columns = [];
        $readRecordNumber = 0;

        if ($this->fileValid($path)) {
            $data = $this->getExcelData($path, $this->sheetName);

            if ($this->skipFirstRow) {
                $firstRow = array_shift($data);
                foreach ($firstRow as $index => $columnHeader) {
                    $columns[$index] = trim($columnHeader) . " [$index]";
                }
            }

            $previewDataRow = $data[$recordNumber] ?? null;

            if (empty($previewDataRow)) {
                $previewDataRow = end($data);
                $readRecordNumber = count($data) - 1;
            } else {
                $readRecordNumber = $recordNumber;
            }

            foreach ($previewDataRow as $index => $columnData) {
                $previewData[$index] = $columnData;
            }

            if (empty($columns)) {
                $columns = array_keys($previewData);
            }
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);
    }

    public function setSettings(array $settings): void
    {
        $this->skipFirstRow = $settings['skipFirstRow'] ?? false;
        $this->sheetName = $settings['sheetName'] ?? 'Sheet1';
        $this->bulkQueue = $settings['bulkQueue'] ?? false;
    }

    private function getExcelData(string $file, string $sheet): array
    {
        $data = [];
        $reader = new XlsxReader();
        $reader->open($file);

        foreach ($reader->getSheetIterator() as $currentSheet) {
            if ($currentSheet->getName() != $sheet) {
                continue;
            }

            foreach ($currentSheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                $dataRow = [];
                foreach ($cells as $cell) {
                    $dataRow[] = $cell->getValue();
                }
                $data[] = $dataRow;
            }
        }

        $reader->close();

        return $data;
    }

    /**
     * @throws WriterNotOpenedException
     * @throws IOException
     * @throws Exception
     */
    private function bulkLoadData(array &$data): void
    {
        $tmpCsv = tempnam(sys_get_temp_dir(), 'pimcore_bulk_load');
        $options = new CSVOptions();
        $options->FIELD_ENCLOSURE = "'";
        $writer = new CSVWriter($options);
        $writer->openToFile($tmpCsv);

        $carbonNow = Carbon::now();
        $db = Db::get();

        foreach ($data as $rowData) {
            if ($this->rowFiltered($rowData)) {
                continue;
            }

            $this->addToIdentifierCache($rowData);

            $json = json_encode($rowData);

            $c = Cell::fromValue($json);
            $cells = [
                Cell::fromValue((int)($carbonNow->getTimestamp() . str_pad((string)$carbonNow->milli, 3, '0'))),
                Cell::fromValue($this->configName),
                $c,
                Cell::fromValue($this->executionType),
                Cell::fromValue(ImportProcessingService::JOB_TYPE_PROCESS)
            ];

            $row = new Row($cells);
            $writer->addRow($row);
        }

        $writer->close();

        $quote = "'";
        $sql = <<<SQL
        LOAD DATA LOCAL INFILE $quote$tmpCsv$quote INTO TABLE bundle_data_hub_data_importer_queue
            FIELDS
                TERMINATED BY ','
                ENCLOSED BY "'"
                ESCAPED BY ''
            LINES TERMINATED BY '\n'

            (timestamp, configName, data, executionType, jobType)
        SQL;

        $db->executeQuery($sql);

        unlink($tmpCsv);
    }
}
