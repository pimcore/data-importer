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

use JmesPath\Parser as Parser;
use JmesPath\Env as JmesPath;
use JmesPath\SyntaxErrorException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;

class JsonFileInterpreter extends AbstractInterpreter
{
    protected string $path;

    /**
     * @var array|null
     */
    protected $cachedContent = null;

    /**
     * @var string|null
     */
    protected $cachedFilePath = null;

    protected function loadDataRaw(string $path): array
    {
        $content = file_get_contents($path);
        return json_decode($this->prepareContent($content), true);
    }

    protected function loadData(string $path): array
    {
        if ($this->cachedFilePath === $path && !empty($this->cachedContent)) {
            $data = $this->loadDataRaw($path);
        } else {
            $data = $this->cachedContent;
        }

        if (!empty($this->path)) {
            return $this->getValueFromPath($data);
        }
        return $data;
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
        $path = $settings['path'];
        if (!empty($path)) {
            try {
                (new Parser)->parse($path);
            } catch (SyntaxErrorException $e) {
                throw new InvalidConfigurationException("Invalid JMESPath expression: " . $e->getMessage());
            }
        }

        $this->path = $path;
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

        $data = $this->loadDataRaw($path);

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

    /**
     * Returns a value from the specified path in a nested array `$data`.
     */
    private function getValueFromPath(array $data): mixed
    {
        return JmesPath::search($this->path, $data);
    }
}
