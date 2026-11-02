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

namespace Pimcore\Bundle\DataImporterBundle\Telemetry;

use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ImportStartEvent;
use Pimcore\Telemetry\TelemetryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Captures a content-never `datahub.import_manual_started` event each time a person starts an import from
 * Studio (pimcore/product-management#1408, row 5: manual runs next to the scheduled/manual
 * configuration counts of the snapshot).
 *
 * Manual only, by construction: the Studio start service is the sole dispatcher of `ImportStartEvent`.
 * Cron runs (`CronExecutionCommand`) and the push API (`PushImportController`) call the preparation
 * service directly and never raise it - the preparation event they share with manual starts is
 * deliberately not subscribed. The trigger is part of the event name; the properties are categorical
 * (adapter, surface, outcome) and the configuration name never leaves.
 *
 * @internal
 */
final readonly class ImportStartTelemetrySubscriber implements EventSubscriberInterface
{
    private const EVENT_IMPORT_MANUAL_STARTED = 'datahub.import_manual_started';

    public function __construct(
        private TelemetryInterface $telemetry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportStartEvent::EVENT_NAME => 'onImportStart',
        ];
    }

    public function onImportStart(ImportStartEvent $event): void
    {
        $this->telemetry->capture(self::EVENT_IMPORT_MANUAL_STARTED, [
            'adapter' => 'data_importer',
            'surface' => 'studio',
            'success' => $event->getImportStart()->isSuccess(),
        ]);
    }
}
