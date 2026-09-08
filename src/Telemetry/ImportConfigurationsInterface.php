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

/**
 * The seam between the usage provider and Data Hub's configuration store, where import configurations live.
 *
 * @internal
 */
interface ImportConfigurationsInterface
{
    /**
     * @return bool|null null when the configuration store could not be read - unknown, not unused
     */
    public function hasActive(): ?bool;
}
