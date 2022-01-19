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

use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;

class JsonFileInterpreter extends AbstractInterpreter
{
    /**
     * @var array|null
     */
    protected $cachedContent = null;

    /**
     * @var string|null
     */
    protected $cachedFilePath = null;

    protected function loadData(string $path): array
    {
        if ($this->cachedFilePath === $path && !empty($this->cachedContent)) {
            $content = file_get_contents($path);

            return json_decode($this->prepareContent($content), true);
        } else {
            return $this->cachedContent;
        }
    }

    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        $data = $this->loadData($path);

        foreach ($data as $dataRow) {
            $this->processImportRow($dataRow);
        }
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }

    /**
     * remove BOM bytes to have a proper UTF-8
     *
     * @param string $content
     *
     * @return string
     */
    protected function prepareContent($content)
    {
        $UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $first3 = substr($content, 0, 3);
        if ($first3 === $UTF8_BOM) {
            $content = substr($content, 3);
        }

        return $content;
    }

    public function fileValid(string $path, bool $originalFilename = false): bool
    {
        $this->cachedContent = null;
        $this->cachedFilePath = null;

        if ($originalFilename) {
            $filename = $path;
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext !== 'json') {
                return false;
            }
        }

        $content = file_get_contents($path);

        $data = json_decode($this->prepareContent($content), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $this->cachedContent = $data;
            $this->cachedFilePath = $path;

            return true;
        } else {
            $this->applicationLogger->error('Reading file ERROR: ' . json_last_error_msg(), [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName
            ]);

            return false;
        }
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
        $readRecordNumber = 0;

        if ($this->fileValid($path)) {
            $data = $this->loadData($path);

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

            $keys = array_keys($previewData);
            $columns = array_combine($keys, $keys);
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);
    }
}
