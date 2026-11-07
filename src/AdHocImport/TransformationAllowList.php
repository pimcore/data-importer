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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;

/**
 * The operators an ad hoc import offers for one column, with the settings each may take. Everything else of the Data
 * Importer (combining columns, other load strategies, importing files) needs a configuration.
 *
 * @internal
 */
final class TransformationAllowList
{
    public const string TRIM = 'trim';

    public const string REPLACE = 'stringReplace';

    public const string MAP_VALUES = 'conditionalConversion';

    public const string SPLIT = 'explode';

    public const string LOAD_OBJECT = 'loadDataObject';

    public const string LOAD_ASSET = 'loadAsset';

    // the conversions to the field's type, tried in this order: a numeric field takes a boolean too, but means a number
    public const array CONVERSIONS = ['numeric', 'date', 'asArray', 'gallery', 'boolean'];

    private const string LOAD_STRATEGY = 'loadStrategy';

    // a reference is given by ID or full path only
    private const array LOAD_STRATEGIES = ['id', 'path'];

    /**
     * Per operator its settings: a list of the values allowed, or null for any text.
     *
     * @var array<string, array<string, list<string>|null>>
     */
    private const array OPERATORS = [
        self::TRIM => ['mode' => ['both', 'left', 'right']],
        self::REPLACE => ['search' => null, 'replace' => null],
        self::MAP_VALUES => ['original' => null, 'converted' => null],
        self::SPLIT => ['delimiter' => null],
        self::LOAD_OBJECT => [self::LOAD_STRATEGY => self::LOAD_STRATEGIES],
        self::LOAD_ASSET => [self::LOAD_STRATEGY => self::LOAD_STRATEGIES],
    ];

    // the load operators' own defaults differ (ID for objects, path for assets); an ad hoc import names one
    private const array DEFAULT_SETTINGS = [
        self::LOAD_OBJECT => [self::LOAD_STRATEGY => 'id'],
        self::LOAD_ASSET => [self::LOAD_STRATEGY => 'id'],
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::OPERATORS);
    }

    /**
     * The step as the Data Importer's pipeline takes it, with its defaults filled in.
     *
     * @return array{type: string, settings: array<string, string>}
     *
     * @throws InvalidArgumentException for an operator or a setting the ad hoc import does not offer
     */
    public static function step(mixed $step, int $position): array
    {
        if (!is_array($step) || !is_string($step['type'] ?? null) || !isset(self::OPERATORS[$step['type']])) {
            throw new InvalidArgumentException(sprintf(
                'Transformation %d is not one an ad hoc import offers.',
                $position
            ));
        }
        $type = $step['type'];
        $settings = $step['settings'] ?? [];
        if (!is_array($settings)) {
            throw new InvalidArgumentException(sprintf('Transformation %d: settings must be an object.', $position));
        }

        $allowed = self::OPERATORS[$type];
        foreach ($settings as $name => $value) {
            if (!array_key_exists($name, $allowed)) {
                throw new InvalidArgumentException(sprintf(
                    'Transformation %d: "%s" has no setting "%s".',
                    $position,
                    $type,
                    $name
                ));
            }
            $values = $allowed[$name];
            if (!is_string($value) || ($values !== null && !in_array($value, $values, true))) {
                throw new InvalidArgumentException(sprintf(
                    'Transformation %d: "%s" is no value of the setting "%s".',
                    $position,
                    is_scalar($value) ? (string) $value : get_debug_type($value),
                    $name
                ));
            }
        }

        return ['type' => $type, 'settings' => [...(self::DEFAULT_SETTINGS[$type] ?? []), ...$settings]];
    }
}
