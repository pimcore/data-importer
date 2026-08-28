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

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Symfony\Component\Config\Util\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
final class XmlFileInterpreter extends AbstractInterpreter
{
    private string $xpath;

    private ?string $schema;

    private ?\DOMDocument $cachedContent = null;

    private ?string $cachedFilePath = null;

    private function loadDataRaw(string $path)
    {
        $schema = $this->schema;

        return XmlUtils::loadFile($path, function ($dom) use ($schema) {
            if (!empty($schema)) {
                return @$dom->schemaValidateSource($schema);
            }

            return true;
        });
    }

    /**
     * @param string $path
     *
     * @return \DOMNodeList
     *
     * @throws InvalidInputException
     */
    private function loadData(string $path)
    {
        if ($this->cachedFilePath !== $path || empty($this->cachedContent)) {
            $dom = $this->loadDataRaw($path);
        } else {
            $dom = $this->cachedContent;
        }

        $xpath = new \DOMXpath($dom);

        $result = $xpath->evaluate($this->xpath);
        if ($result instanceof \DOMNodeList) {
            return $result;
        } else {
            throw new InvalidInputException(sprintf('Item path `%s` not found.', $this->xpath));
        }
    }

    protected function doInterpretFileAndCallProcessRow(string $path): void
    {
        if ($this->getStreamingElementPath() !== null) {
            foreach ($this->streamRecords($path) as $dataRow) {
                $this->processImportRow($dataRow);
            }

            return;
        }

        $records = $this->loadData($path);

        /** @var \DOMElement $item */
        foreach ($records as $item) {
            $this->processImportRow(XmlUtils::convertDomElementToArray($item));
        }
    }

    public function fileValid(string $path, bool $originalFilename = false): bool
    {
        $this->cachedContent = null;
        $this->cachedFilePath = null;

        if ($originalFilename) {
            $filename = $path;
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext !== 'xml') {
                return false;
            }
        }

        try {
            if ($this->getStreamingElementPath() !== null) {
                // stream through the whole document (well-formedness + schema) without
                // building a DOM tree - large files would otherwise exhaust the memory limit
                foreach ($this->streamRecords($path) as $record) {
                    // iterate to let the streaming parser see the whole document
                }

                return true;
            }

            $dom = $this->loadDataRaw($path);
        } catch (XmlParsingException $exception) {
            $message = 'Error validating XML: ' . $exception->getMessage();
            $this->applicationLogger->info($message, [
                'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
            ]);

            return false;
        }

        $this->cachedContent = $dom;
        $this->cachedFilePath = $path;

        return true;
    }

    public function previewData(string $path, int $recordNumber = 0, array $mappedColumns = []): PreviewData
    {
        $previewData = [];
        $columns = [];
        $readRecordNumber = 0;

        if ($this->fileValid($path)) {
            if ($this->getStreamingElementPath() !== null) {
                [$previewData, $readRecordNumber] = $this->readStreamedRecord($path, $recordNumber);
                $previewData = $previewData ?? [];
            } else {
                $records = $this->loadData($path);
                $previewDataItem = $records->item($recordNumber);

                if (empty($previewDataItem)) {
                    $readRecordNumber = $records->count() - 1;
                    $previewDataItem = $records->item($readRecordNumber);
                } else {
                    $readRecordNumber = $recordNumber;
                }

                if (!empty($previewDataItem) && $previewDataItem instanceof \DOMElement) {
                    $previewData = XmlUtils::convertDomElementToArray($previewDataItem);
                }
            }

            if (!empty($previewData)) {
                $keys = array_keys($previewData);
                $columns = array_combine($keys, $keys);
            }
        }

        return new PreviewData($columns, $previewData, $readRecordNumber, $mappedColumns);
    }

    public function setSettings(array $settings): void
    {
        if (empty($settings['xpath'])) {
            throw new InvalidConfigurationException('Empty XPath.');
        }
        $this->xpath = $settings['xpath'];
        $this->schema = $settings['schema'];
    }

    /**
     * Returns the element names of the configured XPath expression when it is simple enough
     * to stream: an absolute path of plain, un-prefixed element names (e.g. `/catalog/product`).
     * Predicates, wildcards, attributes, `//` and namespace prefixes need a full DOM and
     * return null here, falling back to the full-load code path.
     *
     * @return string[]|null
     */
    private function getStreamingElementPath(): ?array
    {
        if (preg_match('#^(/[A-Za-z_][A-Za-z0-9_.\-]*)+$#', $this->xpath) !== 1) {
            return null;
        }

        return explode('/', trim($this->xpath, '/'));
    }

    /**
     * Streams the records matching the configured element path one by one with XMLReader,
     * so the whole document never has to be loaded into memory. The configured XSD schema
     * (if any) is validated incrementally during the same pass.
     *
     * @return \Generator<array>
     *
     * @throws XmlParsingException
     */
    private function streamRecords(string $path): \Generator
    {
        $segments = $this->getStreamingElementPath();
        $targetDepth = count($segments) - 1;

        $reader = new \XMLReader();
        $useInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $schemaFile = null;

        try {
            if (!$reader->open($path, null, LIBXML_NONET)) {
                throw new XmlParsingException(sprintf('Could not open XML file `%s`.', $path));
            }

            if (!empty($this->schema)) {
                $schemaFile = tempnam(sys_get_temp_dir(), 'data_importer_xsd_');
                file_put_contents($schemaFile, $this->schema);
                if (!@$reader->setSchema($schemaFile)) {
                    throw new XmlParsingException($this->buildLibXmlErrorMessage('Invalid XSD schema.'));
                }
            }

            $elementStack = [];
            $keepReading = @$reader->read();

            while ($keepReading) {
                if ($reader->nodeType === \XMLReader::ELEMENT) {
                    // elements in a namespace can never match the un-prefixed path segments,
                    // mirroring how DOMXPath treats un-prefixed name tests
                    $elementStack[$reader->depth] = ($reader->namespaceURI === '') ? $reader->localName : null;

                    if ($reader->depth === $targetDepth && $this->elementStackMatches($elementStack, $segments)) {
                        $node = @$reader->expand(new \DOMDocument());
                        if ($node === false) {
                            throw new XmlParsingException($this->buildLibXmlErrorMessage('Could not expand XML record.'));
                        }
                        if ($node instanceof \DOMElement) {
                            yield XmlUtils::convertDomElementToArray($node);
                        }

                        // skips the subtree that was just expanded
                        $keepReading = @$reader->next();
                        continue;
                    }
                }

                $keepReading = @$reader->read();
            }

            $this->assertNoLibXmlErrors();
        } finally {
            $reader->close();
            if ($schemaFile !== null) {
                @unlink($schemaFile);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }

    /**
     * @param array<int, ?string> $elementStack
     * @param string[] $segments
     */
    private function elementStackMatches(array $elementStack, array $segments): bool
    {
        foreach ($segments as $depth => $segment) {
            if (($elementStack[$depth] ?? null) !== $segment) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws XmlParsingException when the streaming parser recorded any well-formedness
     *                             or schema validation error
     */
    private function assertNoLibXmlErrors(): void
    {
        if (libxml_get_errors() !== []) {
            throw new XmlParsingException($this->buildLibXmlErrorMessage('Invalid XML document.'));
        }
    }

    private function buildLibXmlErrorMessage(string $fallbackMessage): string
    {
        $messages = [];
        foreach (libxml_get_errors() as $error) {
            $messages[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
                LIBXML_ERR_WARNING === $error->level ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ?: 'n/a',
                $error->line,
                $error->column
            );
        }

        return $messages === [] ? $fallbackMessage : implode("\n", $messages);
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

        foreach ($this->streamRecords($path) as $row) {
            $currentRow = $row;
            $currentRecordNumber++;

            if ($currentRecordNumber === $recordNumber && !empty($currentRow)) {
                return [$currentRow, $currentRecordNumber];
            }
        }

        return [$currentRow, max(0, $currentRecordNumber)];
    }
}
