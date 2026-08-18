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
     * an inspection aid). A fan-out displays the first resulting row exactly as it would be
     * queued and exposes the column set of all resulting rows as headers, so every column a
     * listener adds is mappable; columns no resulting row contains are removed.
     *
     * Note: the event is dispatched for the displayed record only - preceding records are not
     * replayed. Listeners that carry state across rows should use isPreview() to fall back to
     * stateless behavior in preview mode.
     */
    public function applyToPreviewData(
        string $configName,
        array $processingConfig,
        PreviewData $previewData,
        array $mappedColumns = []
    ): PreviewData {
        $originalRow = $previewData->getRawData();

        // an empty preview record produces no row event during a real import either
        if ($originalRow === []) {
            return $previewData;
        }

        $event = new PreQueueRowEvent(
            $configName,
            $this->resolveExecutionType($processingConfig),
            $originalRow,
            preview: true
        );
        $this->eventDispatcher->dispatch($event);

        $rows = $event->getRows();
        if ($rows === [$originalRow] || $rows === []) {
            return $previewData;
        }

        $labels = [];
        foreach ($previewData->getDataColumnHeaders() as $columnHeader) {
            $labels[$columnHeader['dataIndex']] = $columnHeader['label'];
        }

        $resultColumns = [];
        foreach ($rows as $resultRow) {
            foreach (array_keys($resultRow) as $index) {
                $resultColumns[$index] = true;
            }
        }

        // drop columns no resulting row contains, keeping the original header order
        foreach (array_keys($labels) as $index) {
            if (!isset($resultColumns[$index]) && !isset($resultColumns[(string) $index])) {
                unset($labels[$index]);
            }
        }

        foreach (array_keys($resultColumns) as $index) {
            if (!array_key_exists($index, $labels) && !array_key_exists((string) $index, $labels)) {
                $labels[$index] = is_int($index) ? "[$index]" : (string) $index;
            }
        }

        // display the first resulting row exactly as it would be queued - no value merging
        return new PreviewData($labels, $rows[0], $previewData->getRecordNumber(), $mappedColumns);
    }

    private function resolveExecutionType(array $processingConfig): string
    {
        return $processingConfig['executionType'] ?? ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL;
    }
}
