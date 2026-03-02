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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Tool\Traits;

use Symfony\Component\Yaml\Yaml;

/**
 * Shared configuration parsing for MCP tools that accept JSON or YAML input.
 *
 * @internal
 */
trait ConfigurationParserTrait
{
    /**
     * Parse a configuration string (JSON or YAML) into an array.
     *
     * @throws \InvalidArgumentException if parsing fails or result is not an array
     */
    private function parseConfiguration(string $config, string $format): array
    {
        if ($format === '') {
            $trimmed = ltrim($config);
            $format = str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')
                ? 'json'
                : 'yaml';
        }

        $format = strtolower($format);

        if ($format === 'json') {
            return json_decode($config, true, 512, JSON_THROW_ON_ERROR);
        }

        $result = Yaml::parse($config);
        if (!is_array($result)) {
            throw new \InvalidArgumentException('Configuration must parse to an array');
        }

        return $result;
    }
}
