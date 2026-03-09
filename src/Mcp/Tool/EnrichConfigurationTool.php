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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\Traits\ConfigurationParserTrait;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
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
final readonly class EnrichConfigurationTool
{
    use ConfigurationParserTrait;

    private const MSG_MISSING_MAPPING = 'Configuration must have mappingConfig';

    public function __construct(
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private ImportProcessingService $importProcessingService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'enrich_configuration',
        description: 'Calculate and add transformationResultType to mapping items. This type is '
            . 'derived from the transformation pipeline and cannot be determined without this tool. '
            . 'Accepts full config or single mapping item (JSON/YAML). Returns enriched data in '
            . 'same format. Call after building config, before validation.'
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
            description: 'Format: "json" or "yaml". Auto-detects if not specified.'
        )]
        string $format = ''
    ): CallToolResult {
        try {
            $detectedFormat = $this->detectInputFormat($configuration, $format);
            $configArray = $this->parseConfiguration($configuration, $format);
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

    private function detectInputFormat(string $configuration, string $format): string
    {
        if ($format !== '') {
            return strtolower($format);
        }

        $trimmed = ltrim($configuration);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')
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
