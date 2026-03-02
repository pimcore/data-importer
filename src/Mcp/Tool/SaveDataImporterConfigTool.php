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
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\StudioBackendBundle\Mcp\McpToolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * MCP tool to update an existing Data Importer configuration.
 *
 * Loads the current configuration to obtain the modification date
 * (for conflict detection), then saves the new configuration.
 *
 * @internal
 */
final readonly class SaveDataImporterConfigTool implements McpToolInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'pimcore_dataimporter_save_configuration',
        description: 'Update an existing Data Importer configuration by name. Replaces the full '
            . 'configuration. Fails if name does not exist (use create_configuration for new ones). '
            . 'Validate with validate_configuration first.'
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Name of the existing configuration to update.'
        )]
        string $name,
        #[Schema(
            type: 'string',
            description: 'The full Data Importer configuration as '
                . 'JSON or YAML string. Must include all sections: '
                . 'general, loaderConfig, interpreterConfig, '
                . 'resolverConfig, processingConfig, mappingConfig.'
        )]
        string $configuration,
        #[Schema(
            type: 'string',
            description: 'Format: "json" or "yaml". Auto-detects '
                . 'if not specified.'
        )]
        string $format = ''
    ): CallToolResult {
        try {
            $configArray = $this->parseConfiguration(
                $configuration,
                $format
            );

            // Load existing configuration
            $config = Configuration::getByName($name);
            if (!$config instanceof Configuration) {
                return new CallToolResult(
                    [
                        new TextContent(
                            json_encode(
                                [
                                    'error' => 'Configuration "' . $name
                                        . '" not found.',
                                    'hint' => 'Use '
                                        . 'pimcore_dataimporter_create_configuration '
                                        . 'to create a new config first.'
                                ],
                                JSON_PRETTY_PRINT
                            )
                        )
                    ],
                    isError: true
                );
            }

            // Ensure general section is consistent
            $configArray['general'] = $configArray['general'] ?? [];
            $configArray['general']['name'] = $name;
            $configArray['general']['active'] =
                $configArray['general']['active'] ?? false;

            $config->setConfiguration($configArray);
            $config->save();

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'success' => true,
                                'name' => $name,
                                'modificationDate' =>
                                    $config->getModificationDate(),
                                'message' => 'Configuration "'
                                    . $name . '" saved successfully.'
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: false
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to save Data Importer configuration',
                [
                    'name' => $name,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            );

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'error' => $e->getMessage()
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: true
            );
        }
    }

    private function parseConfiguration(
        string $config,
        string $format
    ): array {
        if ($format === '') {
            $trimmed = ltrim($config);
            $format = str_starts_with($trimmed, '{')
                || str_starts_with($trimmed, '[')
                ? 'json'
                : 'yaml';
        }

        $format = strtolower($format);

        if ($format === 'json') {
            return json_decode(
                $config,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        $result = Yaml::parse($config);
        if (!is_array($result)) {
            throw new \InvalidArgumentException(
                'Configuration must parse to an array'
            );
        }

        return $result;
    }
}
