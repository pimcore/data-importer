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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Telemetry;

use function array_keys;
use Codeception\Test\Unit;
use function json_encode;
use Pimcore\Bundle\DataImporterBundle\Event\PostPreparationEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ImportStartEvent;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportStartResponse;
use Pimcore\Bundle\DataImporterBundle\Telemetry\ImportStartTelemetrySubscriber;
use Pimcore\Telemetry\TelemetryInterface;

/**
 * The `datahub.import_manual_started` event: one per import a person starts from Studio, never for a
 * scheduled or pushed one.
 */
class ImportStartTelemetrySubscriberTest extends Unit
{
    /**
     * @var list<array{event: string, properties: array<string, mixed>}>
     */
    private array $captured = [];

    /**
     * The Studio start event is dispatched by the Studio start service alone. The preparation event
     * fires for cron and push runs as well, so the subscriber must not listen to it.
     */
    public function testListensToTheStudioStartEventOnly(): void
    {
        $events = ImportStartTelemetrySubscriber::getSubscribedEvents();

        $this->assertSame([ImportStartEvent::EVENT_NAME => 'onImportStart'], $events);
        $this->assertArrayNotHasKey(PostPreparationEvent::class, $events);
    }

    public function testCapturesOneEventPerManualStart(): void
    {
        $this->subscriber()->onImportStart(new ImportStartEvent(new ImportStartResponse(true)));

        $this->assertCount(1, $this->captured);
        $this->assertSame('datahub.import_manual_started', $this->captured[0]['event']);
    }

    /**
     * Content-never: adapter, surface and the outcome - never the configuration name. The trigger is
     * in the event name.
     */
    public function testCapturesOnlyCategoricalProperties(): void
    {
        $this->subscriber()->onImportStart(new ImportStartEvent(new ImportStartResponse(true)));

        $properties = $this->captured[0]['properties'];
        $this->assertSame(['adapter', 'surface', 'success'], array_keys($properties));
        $this->assertSame('data_importer', $properties['adapter']);
        $this->assertSame('studio', $properties['surface']);
        $this->assertTrue($properties['success']);
    }

    public function testReportsAStartThatCouldNotBePreparedAsUnsuccessful(): void
    {
        $this->subscriber()->onImportStart(new ImportStartEvent(new ImportStartResponse(false)));

        $this->assertFalse($this->captured[0]['properties']['success']);
        $this->assertStringNotContainsString('name', (string) json_encode($this->captured[0]['properties']));
    }

    private function subscriber(): ImportStartTelemetrySubscriber
    {
        $telemetry = $this->createStub(TelemetryInterface::class);
        $telemetry->method('capture')->willReturnCallback(
            function (string $event, array $properties = []): void {
                $this->captured[] = ['event' => $event, 'properties' => $properties];
            }
        );

        return new ImportStartTelemetrySubscriber($telemetry);
    }
}
