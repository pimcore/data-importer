<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter;

use Pimcore\Bundle\DataHubBatchImportBundle\PimcoreDataHubBatchImportBundle;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\PreviewData;
use Pimcore\Log\FileObject;

class JsonFileInterpreter extends AbstractInterpreter
{

    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        $content = file_get_contents($path);
        $data = json_decode($content, true);

        foreach($data as $dataRow) {
            $this->processImportRow($dataRow);
        }

    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }

    public function fileValid(string $path, bool $originalFilename = false): bool {

        if($originalFilename) {
            $filename = $path;
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if($ext !== 'json') {
                return false;
            }
        }

        $content = file_get_contents($path);
        json_decode($content, true);
        return (json_last_error() == JSON_ERROR_NONE);
    }


    /**
     * @param string $path
     * @param int $recordNumber
     * @param array $mappedColumns
     * @return PreviewData
     */
    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData
    {

        $previewData = [];
        $columns = [];
        $readRecordNumber = 0;

        if($this->fileValid($path)) {
            $content = file_get_contents($path);
            $data = json_decode($content, true);

            $previewDataRow = $data[$recordNumber] ?? null;

            if(empty($previewDataRow)) {
                $previewDataRow = end($data);
                $readRecordNumber = count($data) - 1;
            } else {
                $readRecordNumber = $recordNumber;
            }

            foreach($previewDataRow as $index => $columnData) {
                $previewData[$index] = $columnData;
            }

            if(empty($columns)) {
                $keys = array_keys($previewData);
                $columns = array_combine($keys, $keys);
            }
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);

    }

}
