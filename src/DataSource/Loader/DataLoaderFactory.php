<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Loader;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;

class DataLoaderFactory
{
    /**
     * @var DataLoaderInterface[]
     */
    protected $dataLoaderBluePrints;

    /**
     * DataLoaderFactory constructor.
     *
     * @param DataLoaderInterface[] $dataLoaderBluePrints
     */
    public function __construct(array $dataLoaderBluePrints)
    {
        $this->dataLoaderBluePrints = $dataLoaderBluePrints;
    }

    public function loadDataLoader(array $configuration)
    {
        if (empty($configuration['type']) || !array_key_exists($configuration['type'], $this->dataLoaderBluePrints)) {
            throw new InvalidConfigurationException('Unknown loader type `' . ($configuration['type'] ?? '') . '`');
        }

        $dataLoader = clone $this->dataLoaderBluePrints[$configuration['type']];
        $dataLoader->setSettings($configuration['settings'] ?? []);

        return $dataLoader;
    }
}
