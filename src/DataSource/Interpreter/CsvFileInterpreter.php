<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter;

use Pimcore\Bundle\DataHubBatchImportBundle\PimcoreDataHubBatchImportBundle;
use Pimcore\Bundle\DataHubBatchImportBundle\Settings\PreviewData;
use Pimcore\Log\FileObject;

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

    public function interpretFile(string $path): void
    {
        $this->resetIdentifierCache();

        if (($handle = fopen($path, "r")) !== false) {
            if($this->skipFirstRow) {
                //load first row and ignore it
                $data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);
            }

            while (($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                $this->processImportRow($data);
            }
            fclose($handle);
        }

        $this->cleanupElements();

        if($this->doArchiveImportFile) {
            $this->applicationLogger->info("Interpreted source file and created queue items.", [
                'component' => PimcoreDataHubBatchImportBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                'fileObject' => new FileObject(file_get_contents($path))
            ]);
        }

        $this->updateExecutionPackageInformation();

    }

    public function setSettings(array $settings): void
    {
        $this->skipFirstRow = $settings['skipFirstRow'] ?? false;
        $this->delimiter = $settings['delimiter'] ?? ',';
        $this->enclosure = $settings['enclosure'] ?? '"';
        $this->escape = $settings['escape'] ?? '\\';
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
        $readRecordNumber = -1;

        if (($handle = fopen($path, "r")) !== false) {
            if($this->skipFirstRow) {
                //load first row and ignore it
                $data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);

                foreach($data as $index => $columnHeader) {
                    $columns[$index] = trim($columnHeader) . " [$index]";
                }

            }

            $previousData = null;
            while ($readRecordNumber < $recordNumber && ($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                $previousData = $data;
                $readRecordNumber++;
            }

            if(empty($data)) {
                $data = $previousData;
            }

            foreach($data as $index => $columnData) {
                $previewData[$index] = $columnData;
            }

            fclose($handle);
        }

        if(empty($columns)) {
            $columns = array_keys($previewData);
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);

    }
}
