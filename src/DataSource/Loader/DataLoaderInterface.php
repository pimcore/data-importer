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

namespace Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Loader;

use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;

interface DataLoaderInterface extends SettingsAwareInterface
{
    /**
     * @return string
     */
    public function loadData(): string;

    public function cleanup(): void;
}
