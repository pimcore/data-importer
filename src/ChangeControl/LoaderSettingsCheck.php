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

use function in_array;
use function is_array;
use function is_string;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderInterface;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Psr\Container\ContainerInterface;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use TypeError;

/**
 * Whether the loader a configuration names could run with the settings it carries, judged by
 * that loader's own setSettings, so the check follows each loader's rules instead of copying them.
 *
 * @internal
 */
final readonly class LoaderSettingsCheck
{
    /** its file can only be uploaded in the editor once the configuration exists */
    private const array SETTINGS_FILLED_AFTER_CREATE = ['upload'];

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
        if (!is_string($type) || $type === '' || !$this->loaders->has($type)
            || in_array($type, self::SETTINGS_FILLED_AFTER_CREATE, true)) {
            return [];
        }

        $loader = $this->loaders->get($type);
        if (!$loader instanceof DataLoaderInterface) {
            return [];
        }

        $settings = $loaderConfig['settings'] ?? [];

        // a setting the loader reads but the proposal lacks fails the import just as an empty one
        set_error_handler(static function (int $severity, string $message): never {
            throw new LoaderSettingsReadException($message, 0, $severity);
        });

        try {
            // loaders are shared services: never configure the one the importer uses
            (clone $loader)->setSettings(is_array($settings) ? $settings : []);
        } catch (InvalidConfigurationException $e) {
            return ['loaderConfig: ' . $e->getMessage()];
        } catch (LoaderSettingsReadException $e) {
            return [sprintf('loaderConfig: the "%s" loader cannot read its settings: %s.', $type, $e->getMessage())];
        } catch (TypeError) {
            return [sprintf('loaderConfig: a setting of the "%s" loader is missing or has the wrong type.', $type)];
        } finally {
            restore_error_handler();
        }

        return [];
    }
}
