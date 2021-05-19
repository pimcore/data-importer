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

use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;

class CsvFileInterpreter extends AbstractInterpreter
{
    /**
     * @var bool
     */
    protected $skipFirstRow;

    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var string
     */
    protected $enclosure;

    /**
     * @var string
     */
    protected $escape;

    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        if (($handle = fopen($path, 'r')) !== false) {
            if ($this->skipFirstRow) {
                //load first row and ignore it
                $data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);
            }

            while (($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                $this->processImportRow($data);
            }
            fclose($handle);
        }
    }

    public function setSettings(array $settings): void
    {
        $this->skipFirstRow = $settings['skipFirstRow'] ?? false;
        $this->delimiter = $settings['delimiter'] ?? ',';
        $this->enclosure = $settings['enclosure'] ?? '"';
        $this->escape = $settings['escape'] ?? '\\';
    }

    public function fileValid(string $path, bool $originalFilename = false): bool
    {
        if ($originalFilename) {
            $filename = $path;
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext !== 'csv') {
                return false;
            }
        }

        $csvMimes = ['text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return in_array($mime, $csvMimes);
    }

    /**
     * @param string $path
     * @param int $recordNumber
     * @param array $mappedColumns
     *
     * @return PreviewData
     */
    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData
    {
        $previewData = [];
        $columns = [];
        $readRecordNumber = -1;

        if ($this->fileValid($path) && ($handle = fopen($path, 'r')) !== false) {
            if ($this->skipFirstRow) {
                //load first row and ignore it
                $data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);

                foreach ($data as $index => $columnHeader) {
                    $columns[$index] = trim($columnHeader) . " [$index]";
                }
            }

            $previousData = null;
            while ($readRecordNumber < $recordNumber && ($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                $previousData = $data;
                $readRecordNumber++;
            }

            if (empty($data)) {
                $data = $previousData;
            }

            foreach ($data as $index => $columnData) {
                $previewData[$index] = $columnData;
            }

            fclose($handle);
        }

        if (empty($columns)) {
            $columns = array_keys($previewData);
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);
    }
}
