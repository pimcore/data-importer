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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Pimcore\Bundle\StudioBackendBundle\Mcp\McpToolInterface;
use Psr\Log\LoggerInterface;

/**
 * MCP tool to retrieve configuration examples.
 *
 * Provides real-world configuration examples demonstrating common
 * import scenarios and best practices. Examples include CSV imports,
 * XML processing, JSON APIs, transformation patterns, and more.
 *
 * @internal
 */
final readonly class GetConfigurationExamplesTool implements McpToolInterface
{
    private const EXAMPLES_PATH = __DIR__ . '/../../../doc/examples';

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'pimcore_dataimporter_get_configuration_examples',
        description: 'Get real-world configuration examples demonstrating ' .
            'common import scenarios. Returns example configurations with ' .
            'explanations of patterns for CSV, XML, JSON imports, ' .
            'transformations, relations, and best practices. Use this to ' .
            'understand typical configuration structures and mapping ' .
            'strategies when creating new imports.'
    )]
    public function execute(): CallToolResult
    {
        try {
            $examples = $this->loadExamples();

            if (empty($examples)) {
                $this->logger->warning(
                    'No configuration examples found',
                    ['path' => self::EXAMPLES_PATH]
                );

                return new CallToolResult(
                    [
                        new TextContent(
                            json_encode(
                                [
                                    'examples' => [],
                                    'message' => 'No examples available'
                                ],
                                JSON_PRETTY_PRINT
                            )
                        )
                    ],
                    isError: false
                );
            }

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            ['examples' => $examples],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: false
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to get configuration examples',
                ['exception' => $e]
            );

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'error' => $e->getMessage(),
                                'type' => get_class($e)
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: true
            );
        }
    }

    private function loadExamples(): array
    {
        $examplesPath = self::EXAMPLES_PATH;

        if (!is_dir($examplesPath)) {
            return [];
        }

        $examples = [];
        $files = glob($examplesPath . '/*.yaml');

        if ($files === false) {
            return [];
        }

        sort($files);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                $this->logger->warning(
                    'Failed to read example file',
                    ['file' => $file]
                );

                continue;
            }

            try {
                $config = \Symfony\Component\Yaml\Yaml::parse($content);
            } catch (\Exception $e) {
                $this->logger->warning(
                    'Failed to parse example YAML',
                    [
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]
                );

                continue;
            }

            $examples[] = [
                'name' => basename($file, '.yaml'),
                'title' => $config['general']['name'] ?? 'Unknown',
                'description' =>
                    $config['general']['description'] ?? '',
                'configuration' => $config,
                'summary' => $this->generateSummary($config)
            ];
        }

        return $examples;
    }

    private function generateSummary(array $config): array
    {
        $loaderType = $config['loaderConfig']['type'] ?? 'unknown';
        $interpreterType = $config['interpreterConfig']['type'] ??
            'unknown';
        $mappingCount = count($config['mappingConfig'] ?? []);
        $classId = $config['resolverConfig']['dataObjectClassId'] ??
            'unknown';

        $operatorTypes = [];
        foreach (($config['mappingConfig'] ?? []) as $mapping) {
            $pipeline = $mapping['transformationPipeline'] ?? [];
            foreach ($pipeline as $op) {
                $type = $op['type'] ?? 'unknown';
                $operatorTypes[$type] = ($operatorTypes[$type] ?? 0) + 1;
            }
        }

        return [
            'loaderType' => $loaderType,
            'interpreterType' => $interpreterType,
            'targetClass' => $classId,
            'mappingCount' => $mappingCount,
            'usedOperators' => array_keys($operatorTypes),
            'operatorCounts' => $operatorTypes
        ];
    }
}
