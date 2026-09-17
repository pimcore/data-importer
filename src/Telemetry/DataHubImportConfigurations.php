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

use Closure;
use Exception;
use function is_array;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Telemetry\DataHubConfigurationUsage;

/**
 * Import configurations are Data Hub configurations of the importer's adapter type, so the question is
 * put to Data Hub's shared, memoised read - the same instance the five Data Hub packages already use,
 * which means this costs no additional read and inherits the location-aware handling (settings store or
 * Symfony config files) that a direct table query would get wrong.
 *
 * @internal
 */
final readonly class DataHubImportConfigurations implements ImportConfigurationsInterface
{
    /**
     * The adapter type this bundle registers with Data Hub.
     */
    private const ADAPTER_TYPE = 'dataImporterDataObject';

    /**
     * @var Closure(): iterable<Configuration>
     */
    private Closure $listConfigurations;

    /**
     * @param (Closure(): iterable<Configuration>)|null $listConfigurations defaults to Data Hub's listing;
     *                                                                       injectable for tests
     */
    public function __construct(
        private DataHubConfigurationUsage $configurations,
        ?Closure $listConfigurations = null,
    ) {
        $this->listConfigurations = $listConfigurations ?? static fn (): array => Configuration::getList();
    }

    public function hasActive(): ?bool
    {
        return $this->configurations->hasActiveOfType([self::ADAPTER_TYPE]);
    }

    /**
     * The shared read knows types and activity only, so the execution configurations come from the same
     * location-aware listing directly - one more pass over the store, once a day.
     */
    public function activeExecutionConfigs(): ?array
    {
        $configs = [];

        // The listing may be lazy, so the walk stays inside the guard: a failure while iterating is the
        // same "unreadable" as one while creating it.
        try {
            foreach (($this->listConfigurations)() as $configuration) {
                if ($configuration->getType() !== self::ADAPTER_TYPE || !$this->isActive($configuration)) {
                    continue;
                }

                $execution = $configuration->getConfiguration()['executionConfig'] ?? [];
                $configs[] = is_array($execution) ? $execution : [];
            }
        } catch (Exception) {
            return null;
        }

        return $configs;
    }

    /**
     * The same truthiness Data Hub's usage reader and the import execution apply to the stored flag.
     */
    private function isActive(Configuration $configuration): bool
    {
        return (bool) $configuration->isActive();
    }
}
