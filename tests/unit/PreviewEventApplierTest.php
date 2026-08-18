<?php declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Event\PreInterpretFileEvent;
use Pimcore\Bundle\DataImporterBundle\Event\PreQueueRowEvent;
use Pimcore\Bundle\DataImporterBundle\Preview\Model\PreviewData;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PreviewEventApplierTest extends Unit
{
    protected $tester;

    private const ROW = ['sku' => 'A-1', 'name' => 'First'];

    private const LABELS = ['sku' => 'sku', 'name' => 'name'];

    private function createApplier(?callable $rowListener = null, ?callable $fileListener = null): PreviewEventApplier
    {
        $dispatcher = new EventDispatcher();
        if ($rowListener !== null) {
            $dispatcher->addListener(PreQueueRowEvent::class, $rowListener);
        }
        if ($fileListener !== null) {
            $dispatcher->addListener(PreInterpretFileEvent::class, $fileListener);
        }

        return new PreviewEventApplier($dispatcher);
    }

    private function createPreviewData(): PreviewData
    {
        return new PreviewData(self::LABELS, self::ROW, 0);
    }

    public function testPreviewDataIsReturnedUnchangedWithoutListeners(): void
    {
        $previewData = $this->createPreviewData();

        $result = $this->createApplier()->applyToPreviewData('test_config', [], $previewData);

        $this->assertSame($previewData, $result);
    }

    public function testListenerAddedColumnsBecomeVisibleAndMappable(): void
    {
        $applier = $this->createApplier(function (PreQueueRowEvent $event): void {
            $this->assertTrue($event->isPreview());
            $row = $event->getOriginalRow();
            $row['path'] = '/products/' . $row['sku'];
            $event->setRows([$row]);
        });

        $result = $applier->applyToPreviewData('test_config', [], $this->createPreviewData());

        $this->assertSame('/products/A-1', $result->getRawData()['path']);
        $this->assertContains(
            ['id' => 'path', 'dataIndex' => 'path', 'label' => 'path'],
            $result->getDataColumnHeaders()
        );
    }

    public function testIntegerIndexedColumnsGetTheCsvStyleLabel(): void
    {
        $applier = $this->createApplier(function (PreQueueRowEvent $event): void {
            $event->setRows([[0 => 'A-1', 1 => 'First', 2 => 'synthetic']]);
        });

        $previewData = new PreviewData([0 => 'sku [0]', 1 => 'name [1]'], [0 => 'A-1', 1 => 'First'], 0);
        $result = $applier->applyToPreviewData('test_config', [], $previewData);

        $this->assertContains(
            ['id' => '2', 'dataIndex' => '2', 'label' => '[2]'],
            $result->getDataColumnHeaders()
        );
    }

    public function testSkippedRowStaysVisibleInPreview(): void
    {
        $applier = $this->createApplier(function (PreQueueRowEvent $event): void {
            $event->skipRow();
        });

        $result = $applier->applyToPreviewData('test_config', [], $this->createPreviewData());

        $this->assertSame(self::ROW, $result->getRawData());
    }

    public function testFanOutShowsFirstRowAndExposesColumnsOfAllRows(): void
    {
        $applier = $this->createApplier(function (PreQueueRowEvent $event): void {
            $row = $event->getOriginalRow();
            $event->setRows([
                $row + ['year' => 2024],
                $row + ['year' => 2025, 'extra' => 'x'],
            ]);
        });

        $result = $applier->applyToPreviewData('test_config', [], $this->createPreviewData());

        $this->assertSame(2024, $result->getRawData()['year'], 'first fan-out row is displayed');
        $this->assertArrayNotHasKey(
            'extra',
            $result->getRawData(),
            'the displayed row must be exactly the first queued row - no values merged from later rows'
        );
        $this->assertContains(
            ['id' => 'extra', 'dataIndex' => 'extra', 'label' => 'extra'],
            $result->getDataColumnHeaders(),
            'columns unique to later fan-out rows are still mappable'
        );
    }

    public function testColumnsRemovedByTheListenerDisappearFromTheHeaders(): void
    {
        $applier = $this->createApplier(function (PreQueueRowEvent $event): void {
            $row = $event->getOriginalRow();
            unset($row['name']);
            $event->setRows([$row]);
        });

        $result = $applier->applyToPreviewData('test_config', [], $this->createPreviewData());

        $this->assertSame(['sku' => 'A-1'], $result->getRawData());
        $this->assertNotContains(
            ['id' => 'name', 'dataIndex' => 'name', 'label' => 'name'],
            $result->getDataColumnHeaders(),
            'a column no queued row contains must not stay visible or mappable'
        );
    }

    public function testEmptyPreviewRecordDispatchesNoEvent(): void
    {
        $applier = $this->createApplier(function (PreQueueRowEvent $event): void {
            $this->fail('a real import emits no row event for an empty source, so preview must not either');
        });

        $previewData = new PreviewData([], [], -1);
        $result = $applier->applyToPreviewData('test_config', [], $previewData);

        $this->assertSame($previewData, $result);
    }

    public function testFileListenerCanReplaceThePreviewPath(): void
    {
        $applier = $this->createApplier(null, function (PreInterpretFileEvent $event): void {
            $this->assertTrue($event->isPreview());
            $event->setPath('/tmp/replacement.csv');
        });

        $this->assertSame(
            '/tmp/replacement.csv',
            $applier->applyToPath('test_config', [], '/tmp/original.csv')
        );
    }
}
