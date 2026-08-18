<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Preview;

use Pimcore\Bundle\DataImporterBundle\Event\PreInterpretFileEvent;
use Pimcore\Bundle\DataImporterBundle\Event\PreQueueRowEvent;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies the interpretation-stage events (PreInterpretFileEvent, PreQueueRowEvent) to the
 * Studio preview, so the preview and the mapping UI show the same file and columns an actual
 * import would produce - including columns a PreQueueRowEvent listener adds.
 *
 * @internal
 */
final readonly class PreviewEventApplier
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Lets PreInterpretFileEvent listeners rewrite the preview file path, mirroring what
     * happens at the start of an actual import.
     */
    public function applyToPath(string $configName, array $processingConfig, string $path): string
    {
        $event = new PreInterpretFileEvent(
            $configName,
            $this->resolveExecutionType($processingConfig),
            $path,
            preview: true
        );
        $this->eventDispatcher->dispatch($event);

        return $event->getPath();
    }

    /**
     * Lets PreQueueRowEvent listeners rewrite the preview record, mirroring what happens to
     * every row of an actual import. A skipped row stays visible unmodified (the preview is
     * an inspection aid); a fan-out shows the first resulting row and exposes the columns of
     * all resulting rows, so every column a listener adds is mappable.
     */
    public function applyToPreviewData(
        string $configName,
        array $processingConfig,
        PreviewData $previewData,
        array $mappedColumns = []
    ): PreviewData {
        $originalRow = $previewData->getRawData();

        $event = new PreQueueRowEvent(
            $configName,
            $this->resolveExecutionType($processingConfig),
            $originalRow,
            preview: true
        );
        $this->eventDispatcher->dispatch($event);

        $rows = $event->getRows();
        if ($rows === [$originalRow]) {
            return $previewData;
        }

        $labels = [];
        foreach ($previewData->getDataColumnHeaders() as $columnHeader) {
            $labels[$columnHeader['dataIndex']] = $columnHeader['label'];
        }

        $row = $rows[0] ?? $originalRow;
        foreach ($rows as $additionalRow) {
            foreach ($additionalRow as $index => $value) {
                if (!array_key_exists($index, $row)) {
                    $row[$index] = $value;
                }
            }
        }

        foreach (array_keys($row) as $index) {
            if (!array_key_exists($index, $labels) && !array_key_exists((string) $index, $labels)) {
                $labels[$index] = is_int($index) ? "[$index]" : (string) $index;
            }
        }

        return new PreviewData($labels, $row, $previewData->getRecordNumber(), $mappedColumns);
    }

    private function resolveExecutionType(array $processingConfig): string
    {
        return $processingConfig['executionType'] ?? ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL;
    }
}
