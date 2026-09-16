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

use function is_string;
use Pimcore\Telemetry\Snapshot\SnapshotCollectorInterface;

/**
 * The `data_importer.*` snapshot namespace: how the active import configurations are triggered.
 *
 * An import is scheduled when its execution configuration can fire on its own - a cron schedule with a
 * cron definition (the default schedule type when none is set) or a one-off job with its date, the two
 * cases the bundle's scheduler factory accepts. Everything else runs only when somebody starts it, in
 * Studio or through the push endpoint.
 *
 * Content-never: two counts. Cron definitions and dates never leave. A store that cannot be read leaves
 * the counts absent rather than reporting zeros.
 *
 * @internal
 */
final readonly class DataImporterSnapshotCollector implements SnapshotCollectorInterface
{
    private const SCHEMA_VERSION = 1;

    private const SCHEDULE_JOB = 'job';

    public function __construct(
        private ImportConfigurationsInterface $configurations,
    ) {
    }

    public function getNamespace(): string
    {
        return 'data_importer';
    }

    public function collect(): array
    {
        $metrics = ['schema_version' => self::SCHEMA_VERSION];
        $executionConfigs = $this->configurations->activeExecutionConfigs();

        if ($executionConfigs === null) {
            return $metrics;
        }

        $scheduled = 0;

        foreach ($executionConfigs as $execution) {
            $scheduled += $this->isScheduled($execution) ? 1 : 0;
        }

        $metrics['config_count_scheduled'] = $scheduled;
        $metrics['config_count_manual'] = count($executionConfigs) - $scheduled;

        return $metrics;
    }

    /**
     * @param array<string, mixed> $execution
     */
    private function isScheduled(array $execution): bool
    {
        if (($execution['scheduleType'] ?? null) === self::SCHEDULE_JOB) {
            return $this->hasValue($execution['scheduledAt'] ?? null);
        }

        return $this->hasValue($execution['cronDefinition'] ?? null);
    }

    private function hasValue(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
