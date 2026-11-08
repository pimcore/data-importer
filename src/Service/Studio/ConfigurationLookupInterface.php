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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataHubBundle\Configuration;

/**
 * Data Hub finds its configurations statically; this lets a service be tested without a stored one.
 *
 * @internal
 */
interface ConfigurationLookupInterface
{
    public function getByName(string $name): ?Configuration;
}
