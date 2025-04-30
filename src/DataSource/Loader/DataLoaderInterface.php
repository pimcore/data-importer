<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Loader;

use Pimcore\Bundle\DataImporterBundle\Settings\SettingsAwareInterface;

interface DataLoaderInterface extends SettingsAwareInterface
{
    /**
     * Load data from source, eventually create a temporary file somewhere
     * and return the path to the data
     *
     * @return string path to the data
     */
    public function loadData(): string;

    /**
     * Cleanup temporary file if necessary
     */
    public function cleanup(): void;
}
