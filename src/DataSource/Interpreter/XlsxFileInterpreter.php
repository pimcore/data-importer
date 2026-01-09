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
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class XlsxFileInterpreter extends AbstractInterpreter implements SchemaAwareInterface
{
    /**
     * @var bool
     */
    protected $skipFirstRow;

    /**
     * @var string
     */
    protected $sheetName;

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

        if ($this->fileValid($path)) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadSheet = $reader->load($path);

            $spreadSheet->setActiveSheetIndexByName($this->sheetName);

            $data = $spreadSheet->getActiveSheet()->toArray();

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
    }

    public function getSchemaDescription(): string
    {
        return 'Interpret Excel (.xlsx) file data';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $treeBuilder = new TreeBuilder('settings');
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->booleanNode('skipFirstRow')
                    ->defaultValue(false)
                    ->info('Skip the first row (header row)')
                ->end()
                ->scalarNode('sheetName')
                    ->defaultValue('Sheet1')
                    ->info('Name of the Excel sheet to read')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
