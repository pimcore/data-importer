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
use Pimcore\Bundle\DataImporterBundle\Event\PreQueueRowEvent;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;

class PreQueueRowEventTest extends Unit
{
    protected $tester;

    private const ROW = ['sku' => 'A-1', 'name' => 'First'];

    private function createEvent(): PreQueueRowEvent
    {
        return new PreQueueRowEvent(
            'test_config',
            ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
            self::ROW
        );
    }

    public function testInitialStatePassesRowThroughUnchanged(): void
    {
        $event = $this->createEvent();

        $this->assertSame([self::ROW], $event->getRows());
        $this->assertSame(self::ROW, $event->getOriginalRow());
        $this->assertFalse($event->isRowSkipped());
        $this->assertFalse($event->shouldKeepSkippedRowInIdentifierCache());
        $this->assertFalse($event->isPreview());
    }

    public function testSetRowsModifiesTheRow(): void
    {
        $event = $this->createEvent();
        $modified = self::ROW + ['path' => '/products/A-1'];

        $event->setRows([$modified]);

        $this->assertSame([$modified], $event->getRows());
        $this->assertSame(self::ROW, $event->getOriginalRow(), 'original row must stay untouched');
        $this->assertFalse($event->isRowSkipped());
    }

    public function testSetRowsFansOutAndReindexes(): void
    {
        $event = $this->createEvent();
        $rowA = self::ROW + ['year' => 2024];
        $rowB = self::ROW + ['year' => 2025];

        $event->setRows([3 => $rowA, 7 => $rowB]);

        $this->assertSame([$rowA, $rowB], $event->getRows());
    }

    public function testSetRowsWithEmptyArraySkipsTheRow(): void
    {
        $event = $this->createEvent();

        $event->setRows([]);

        $this->assertTrue($event->isRowSkipped());
        $this->assertFalse($event->shouldKeepSkippedRowInIdentifierCache());
    }

    public function testSkipRowDefaultsToNotKeepingTheIdentifier(): void
    {
        $event = $this->createEvent();

        $event->skipRow();

        $this->assertSame([], $event->getRows());
        $this->assertTrue($event->isRowSkipped());
        $this->assertFalse($event->shouldKeepSkippedRowInIdentifierCache());
    }

    public function testSkipRowCanKeepTheIdentifierForCleanup(): void
    {
        $event = $this->createEvent();

        $event->skipRow(keepInCleanupIdentifierCache: true);

        $this->assertTrue($event->isRowSkipped());
        $this->assertTrue($event->shouldKeepSkippedRowInIdentifierCache());
    }

    public function testPreviewFlag(): void
    {
        $event = new PreQueueRowEvent(
            'test_config',
            ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL,
            self::ROW,
            preview: true
        );

        $this->assertTrue($event->isPreview());
    }
}
