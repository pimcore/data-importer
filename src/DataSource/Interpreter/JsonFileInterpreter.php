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

use JmesPath\Env as JmesPath;
use JmesPath\Parser as Parser;
use JmesPath\SyntaxErrorException;
use JsonMachine\Exception\JsonMachineException;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;

/**
 * @internal
 */
class JsonFileInterpreter extends AbstractInterpreter
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    protected string $path;

    protected ?array $cachedContent = null;

    protected ?string $cachedFilePath = null;

    protected function loadDataRaw(string $path): array
    {
        $content = file_get_contents($path);

        return json_decode($this->prepareContent($content), true);
    }

    protected function loadData(string $path): array
    {
        if ($this->cachedFilePath !== $path || $this->cachedContent === null) {
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
        if ($this->getStreamingJsonPointer() !== null) {
            foreach ($this->streamItems($path) as $dataRow) {
                $this->processImportRow($dataRow);
            }

            return;
        }

        $data = $this->loadData($path);

        foreach ($data as $dataRow) {
            $this->processImportRow($dataRow);
        }
    }

    public function setSettings(array $settings): void
    {
        $path = $settings['path'] ?? '';
        if (!empty($path)) {
            try {
                (new Parser)->parse($path);
            } catch (SyntaxErrorException $e) {
                throw new InvalidConfigurationException('Invalid JMESPath expression: ' . $e->getMessage());
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
        $first3 = substr($content, 0, 3);
        if ($first3 === self::UTF8_BOM) {
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

        if ($this->getStreamingJsonPointer() !== null) {
            return $this->validateStreamed($path);
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
            if ($this->getStreamingJsonPointer() !== null) {
                [$previewDataRow, $readRecordNumber] = $this->readStreamedRecord($path, $recordNumber);
            } else {
                $data = $this->loadData($path);

                $previewDataRow = $data[$recordNumber] ?? null;

                if (empty($previewDataRow)) {
                    $previewDataRow = end($data);
                    $readRecordNumber = count($data) - 1;
                } else {
                    $readRecordNumber = $recordNumber;
                }
            }

            foreach ($previewDataRow ?? [] as $index => $columnData) {
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

    /**
     * Returns the JSON pointer equivalent of the configured JMESPath expression when the
     * expression is simple enough to stream (empty, or a plain dotted field path). Complex
     * JMESPath expressions (filters, projections, functions, ...) need the whole document
     * in memory and return null here, falling back to the full-load code path.
     */
    protected function getStreamingJsonPointer(): ?string
    {
        if (empty($this->path)) {
            return '';
        }

        // any expression reaching this point already passed the JMESPath parser, so a
        // loose word-character check is enough to recognize a plain dotted field path
        if (preg_match('/^\w+(\.\w+)*$/', $this->path) === 1) {
            return '/' . str_replace('.', '/', $this->path);
        }

        return null;
    }

    /**
     * Streams the records below the configured path one by one so the whole file never
     * has to be decoded into memory at once.
     *
     * @return \Generator<array>
     */
    protected function streamItems(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            $this->skipByteOrderMark($handle);

            $items = Items::fromStream($handle, [
                'pointer' => $this->getStreamingJsonPointer(),
                'decoder' => new ExtJsonDecoder(true),
            ]);

            foreach ($items as $item) {
                yield $item;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Validates the file by streaming through all records, keeping memory usage bounded.
     */
    private function validateStreamed(string $path): bool
    {
        try {
            // iterate to let the streaming parser see the whole document
            iterator_count($this->streamItems($path));

            return true;
        } catch (JsonMachineException $exception) {
            $this->applicationLogger->error('Reading file ERROR: ' . $exception->getMessage(), [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName
            ]);

            return false;
        }
    }

    /**
     * Streams up to the requested record and returns it together with the record number that
     * was actually read (the last record when the requested one is out of range).
     *
     * @return array{0: ?array, 1: int}
     */
    private function readStreamedRecord(string $path, int $recordNumber): array
    {
        $currentRecordNumber = -1;
        $currentRow = null;

        foreach ($this->streamItems($path) as $row) {
            $currentRow = $row;
            $currentRecordNumber++;

            if ($currentRecordNumber === $recordNumber && !empty($currentRow)) {
                return [$currentRow, $currentRecordNumber];
            }
        }

        return [$currentRow, max(0, $currentRecordNumber)];
    }

    private function skipByteOrderMark($handle): void
    {
        $bom = fread($handle, strlen(self::UTF8_BOM));
        if (0 !== strncmp(self::UTF8_BOM, (string)$bom, strlen(self::UTF8_BOM))) {
            rewind($handle);
        }
    }
}
