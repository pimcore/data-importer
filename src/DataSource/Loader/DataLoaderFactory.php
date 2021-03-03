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
