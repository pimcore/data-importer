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

namespace Pimcore\Bundle\DataImporterBundle\Settings;

use function preg_match;

/**
 * What a configuration name — and anything else that ends up in a preview file path — may
 * look like: a file name and a YAML key, and never a path segment.
 *
 * @internal
 */
final class ConfigurationName
{
    private const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]*$/';

    private function __construct()
    {
    }

    public static function isValid(string $name): bool
    {
        return preg_match(self::PATTERN, $name) === 1;
    }
}
