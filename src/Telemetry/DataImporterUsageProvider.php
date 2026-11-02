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

use Pimcore\Telemetry\Usage\BundleUsageProviderInterface;

/**
 * Is the Data Importer set up, or merely installed?
 *
 * "Used" means at least one import configuration is **active** - the L3 question every `usage.*` key
 * answers. Active rather than merely existing: a disabled import is one somebody built and then switched
 * off, which is the opposite of adoption.
 *
 * Whether an import has actually run is the L4 fact; it lives in
 * `bundle_data_hub_data_importer_last_execution` and is deliberately not what this key reports, so the
 * namespace stays comparable bundle to bundle.
 *
 * Content-never: a boolean. Configuration names are never emitted.
 *
 * @internal
 */
final readonly class DataImporterUsageProvider implements BundleUsageProviderInterface
{
    public function __construct(
        private ImportConfigurationsInterface $configurations,
    ) {
    }

    public function getBundleKey(): string
    {
        return 'data_importer';
    }

    public function isUsed(): ?bool
    {
        return $this->configurations->hasActive();
    }
}
