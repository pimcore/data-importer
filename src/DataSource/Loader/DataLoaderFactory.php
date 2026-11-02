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

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;

/**
 * @internal
 */
final class DataLoaderFactory
{
    /**
     * @param DataLoaderInterface[] $dataLoaderBluePrints
     */
    public function __construct(
        private readonly array $dataLoaderBluePrints,
    ) {
    }

    /**
     * @param array $configuration
     *
     * @return DataLoaderInterface
     *
     * @throws InvalidConfigurationException
     */
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
