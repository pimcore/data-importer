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
use Psr\Container\ContainerInterface;
use function sprintf;

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

    /** a loader's own message names one setting; the agent needs the whole shape to fix it */
    private const array SETTINGS_SHAPES = [
        'http' => 'The "http" loader needs settings.schema ("https://" or "http://") and settings.url '
            . '(the address without its scheme, e.g. "example.com/feed.json").',
    ];

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

        // a missing type is the create check's to name
        if (!is_string($type) || $type === '' || in_array($type, self::SETTINGS_FILLED_AFTER_CREATE, true)) {
            return [];
        }

        // a review amendment never met the vocabulary the propose tool refuses an unknown type with
        if (!$this->loaders->has($type)) {
            return [sprintf('loaderConfig.type: "%s" is not a loader here.', $type)];
        }

        $loader = $this->loaders->get($type);
        if (!$loader instanceof DataLoaderInterface) {
            return [];
        }

        $settings = $loaderConfig['settings'] ?? [];
        $problem = SettingsRead::problem(
            'loaderConfig',
            sprintf('the "%s" loader', $type),
            static function () use ($loader, $settings): void {
                // loaders are shared services: never configure the one the importer uses
                (clone $loader)->setSettings(is_array($settings) ? $settings : []);
            },
        );

        return $problem === null ? [] : [$this->withShape($type, $problem)];
    }

    private function withShape(string $type, string $problem): string
    {
        $shape = self::SETTINGS_SHAPES[$type] ?? null;

        return $shape === null ? $problem : $problem . ' ' . $shape;
    }
}
