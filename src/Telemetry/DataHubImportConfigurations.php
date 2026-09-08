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

    public function __construct(
        private DataHubConfigurationUsage $configurations,
    ) {
    }

    public function hasActive(): ?bool
    {
        return $this->configurations->hasActiveOfType([self::ADAPTER_TYPE]);
    }
}
