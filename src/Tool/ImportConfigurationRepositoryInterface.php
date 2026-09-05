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

namespace Pimcore\Bundle\DataImporterBundle\Tool;

use Pimcore\Bundle\DataHubBundle\Configuration;

/**
 * Reads the Data Hub configurations this bundle owns.
 *
 * A seam over Configuration's static finders, so callers can be unit tested without standing up
 * a container and a settings store.
 *
 * @internal
 */
interface ImportConfigurationRepositoryInterface
{
    /**
     * Every Data Importer configuration the current user may read.
     *
     * @return list<Configuration>
     */
    public function findReadable(): array;

    /**
     * One Data Importer configuration by name, or null when it does not exist, is of another
     * type, or the current user may not read it.
     */
    public function findReadableByName(string $name): ?Configuration;
}
