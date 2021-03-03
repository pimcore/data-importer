<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
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
