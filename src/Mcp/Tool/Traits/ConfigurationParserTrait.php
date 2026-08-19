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

use function is_array;
use const JSON_THROW_ON_ERROR;
use function ltrim;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Exception\InvalidMcpToolArgumentException;
use function sprintf;
use function str_starts_with;
use function strtolower;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Shared configuration parsing for MCP tools that accept JSON or YAML input.
 *
 * Everything it rejects is rejected as InvalidMcpToolArgumentException, the one type the shared
 * error handler forwards verbatim. A malformed body is the caller's own payload, so telling it
 * what is wrong is the whole point; a generic "internal error, see the log" is useless to an
 * agent that cannot read the log.
 *
 * @internal
 */
trait ConfigurationParserTrait
{
    private const string FORMAT_JSON = 'json';

    private const string FORMAT_YAML = 'yaml';

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidMcpToolArgumentException
     */
    private function parseConfiguration(string $config, string $format): array
    {
        $format = $format === '' ? $this->detectFormat($config) : strtolower($format);

        if ($format !== self::FORMAT_JSON && $format !== self::FORMAT_YAML) {
            throw new InvalidMcpToolArgumentException(sprintf(
                'Unknown format "%s". Use "json" or "yaml", or omit it to auto-detect.',
                $format
            ));
        }

        $parsed = $format === self::FORMAT_JSON
            ? $this->parseJson($config)
            : $this->parseYaml($config);

        if (!is_array($parsed)) {
            throw new InvalidMcpToolArgumentException(
                'The configuration must be an object, not a bare scalar or list.'
            );
        }

        return $parsed;
    }

    private function detectFormat(string $config): string
    {
        $trimmed = ltrim($config);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')
            ? self::FORMAT_JSON
            : self::FORMAT_YAML;
    }

    /**
     * @throws InvalidMcpToolArgumentException
     */
    private function parseJson(string $config): mixed
    {
        try {
            return json_decode($config, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new InvalidMcpToolArgumentException(
                sprintf('The configuration is not valid JSON: %s', $e->getMessage())
            );
        }
    }

    /**
     * @throws InvalidMcpToolArgumentException
     */
    private function parseYaml(string $config): mixed
    {
        try {
            return Yaml::parse($config);
        } catch (ParseException $e) {
            throw new InvalidMcpToolArgumentException(
                sprintf('The configuration is not valid YAML: %s', $e->getMessage())
            );
        }
    }
}
