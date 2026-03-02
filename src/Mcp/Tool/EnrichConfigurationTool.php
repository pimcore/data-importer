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
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\StudioBackendBundle\Mcp\McpToolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * MCP tool to enrich Data Importer configurations with transformation
 * result types.
 *
 * This tool calculates and adds the transformationResultType field to all
 * mapping items in a configuration. This field is derived from the
 * transformation pipeline and cannot be guessed by LLMs. It's useful for:
 * - Understanding data flow through transformation pipelines
 * - Selecting appropriate dataTarget configurations
 * - Debugging transformation pipeline errors
 *
 * @internal
 */
final readonly class EnrichConfigurationTool implements McpToolInterface
{
    private const MSG_ERROR_PARSING_CONFIG = 'Error parsing configuration: ';

    private const MSG_MISSING_MAPPING = 'Configuration must have mappingConfig';

    private const INFO_DEFAULT_JSON = 'Default format is JSON';

    public function __construct(
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private ImportProcessingService $importProcessingService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'pimcore_dataimporter_enrich_configuration',
        description: 'Enrich a Data Importer configuration by calculating '
            . 'and adding transformationResultType to all mapping items. '
            . 'This field is derived from transformation pipelines and '
            . 'helps understand data flow. Use this after building a '
            . 'configuration to ensure all mapping items have correct '
            . 'result types before validation. Accepts full configuration '
            . 'or single mapping item. Returns enriched configuration in '
            . 'same format as input. IMPORTANT: When using YAML format, '
            . 'ensure settings fields are nested YAML structures, NOT '
            . 'JSON strings.'
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Full Data Importer configuration or single '
                . 'mapping item as JSON or YAML string. For full config, '
                . 'must include general.name and mappingConfig array. '
                . 'For single item, must include label, dataSourceIndex, '
                . 'transformationPipeline, and dataTarget. For YAML: use '
                . 'nested structures for all settings fields.'
        )]
        string $configuration,
        #[Schema(
            type: 'string',
            description: 'Format: "json" or "yaml". ' . self::INFO_DEFAULT_JSON
        )]
        string $format = ''
    ): CallToolResult {
        try {
            // Auto-detect format if not specified
            $detectedFormat = $format !== '' ? $format : $this->detectFormat($configuration);
            $configArray = $this->parseConfiguration($configuration, $detectedFormat);
            $isSingleItem = $this->isSingleMappingItem($configArray);

            if ($isSingleItem) {
                $enrichedItem = $this->enrichSingleMappingItem(
                    $configArray,
                    'temp'
                );

                return $this->createSuccessResult(
                    $enrichedItem,
                    $detectedFormat
                );
            }

            $enrichedConfig = $this->enrichFullConfiguration(
                $configArray
            );

            return $this->createSuccessResult(
                $enrichedConfig,
                $detectedFormat
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Error enriching configuration: ' . $e->getMessage(),
                ['exception' => $e]
            );

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'error' => 'Failed to enrich configuration',
                                'message' => $e->getMessage()
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
        string $configuration,
        string $format
    ): array {
        $format = $format !== '' ? $format : $this->detectFormat($configuration);

        try {
            if ($format === 'yaml') {
                return Yaml::parse($configuration);
            }

            return json_decode($configuration, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                self::MSG_ERROR_PARSING_CONFIG . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function detectFormat(string $configuration): string
    {
        $trimmed = ltrim($configuration);

        return str_starts_with($trimmed, '{') ||
            str_starts_with($trimmed, '[')
            ? 'json'
            : 'yaml';
    }

    private function isSingleMappingItem(array $config): bool
    {
        return isset($config['label']) &&
            isset($config['dataSourceIndex']) &&
            isset($config['dataTarget']) &&
            !isset($config['general']) &&
            !isset($config['mappingConfig']);
    }

    private function enrichSingleMappingItem(
        array $mappingItem,
        string $configName
    ): array {
        $mappingConfiguration = $this->mappingConfigurationFactory
            ->loadMappingConfigurationItem($configName, $mappingItem, false);

        $resultType = $this->importProcessingService
            ->evaluateTransformationResultDataType($mappingConfiguration);

        $mappingItem['transformationResultType'] = $resultType;

        return $mappingItem;
    }

    private function enrichFullConfiguration(
        array $config
    ): array {
        if (!isset($config['mappingConfig']) ||
            !is_array($config['mappingConfig'])
        ) {
            throw new \InvalidArgumentException(self::MSG_MISSING_MAPPING);
        }

        $name = $config['general']['name'] ?? 'temp';

        // Handle both old format (mappingConfig as array of items)
        // and new format (mappingConfig.mappingItems)
        if (isset($config['mappingConfig']['mappingItems'])) {
            foreach ($config['mappingConfig']['mappingItems'] as $index => $mappingItem) {
                $config['mappingConfig']['mappingItems'][$index] = $this->enrichSingleMappingItem(
                    $mappingItem,
                    $name
                );
            }
        } else {
            // Old format: mappingConfig is direct array of items
            foreach ($config['mappingConfig'] as $index => $mappingItem) {
                $config['mappingConfig'][$index] = $this->enrichSingleMappingItem(
                    $mappingItem,
                    $name
                );
            }
        }

        return $config;
    }

    private function createSuccessResult(
        array $data,
        string $format
    ): CallToolResult {
        if ($format === 'yaml') {
            $content = Yaml::dump($data, 10, 2);
        } else {
            $content = json_encode($data, JSON_PRETTY_PRINT);
        }

        return new CallToolResult(
            [new TextContent($content)],
            isError: false
        );
    }
}
