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

namespace Pimcore\Bundle\DataImporterBundle\ChangeControl;

use function is_array;
use function is_string;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderInterface;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Psr\Container\ContainerInterface;

/**
 * Whether the loader a configuration names could run with the settings it carries, judged by
 * that loader's own setSettings, so the check follows each loader's rules instead of copying them.
 *
 * @internal
 */
final readonly class LoaderSettingsCheck
{
    /**
     * @param ContainerInterface $loaders loader type => loader
     */
    public function __construct(private ContainerInterface $loaders)
    {
    }

    /**
     * @param array<string, mixed> $state
     *
     * @return list<string>
     */
    public function problems(array $state): array
    {
        $loaderConfig = $state['loaderConfig'] ?? null;
        $type = is_array($loaderConfig) ? ($loaderConfig['type'] ?? null) : null;

        // an unknown or missing type is the vocabulary's and the create check's to name
        if (!is_string($type) || $type === '' || !$this->loaders->has($type)) {
            return [];
        }

        $loader = $this->loaders->get($type);
        if (!$loader instanceof DataLoaderInterface) {
            return [];
        }

        $settings = $loaderConfig['settings'] ?? [];

        try {
            // loaders are shared services: never configure the one the importer uses
            (clone $loader)->setSettings(is_array($settings) ? $settings : []);
        } catch (InvalidConfigurationException $e) {
            return ['loaderConfig: ' . $e->getMessage()];
        }

        return [];
    }
}
