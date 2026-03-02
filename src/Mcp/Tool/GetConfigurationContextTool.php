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
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Pimcore\Bundle\StudioBackendBundle\Mcp\McpToolInterface;
use Psr\Log\LoggerInterface;

/**
 * Comprehensive MCP tool to retrieve all configuration context.
 *
 * Combines schema, operators, targets, and class information in a single
 * call to minimize latency and token usage.
 *
 * @internal
 */
final readonly class GetConfigurationContextTool implements McpToolInterface
{
    public function __construct(
        private ConfigurationSchemaService $configurationSchemaService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'pimcore_dataimporter_get_configuration_context',
        description: 'Get all context needed to build Data Importer configurations. '
            . 'Returns: schema (JSON schema for valid configs), transformation_operators '
            . '(available operators with acceptedInputTypes and outputType — chain operators '
            . 'where output types match next input types), data_target_types (field, '
            . 'classification store, asset metadata), available_classes. Optional: '
            . 'field_type_matrix (when classId given — which fields accept which data types), '
            . 'examples (when includeExamples=true). Call this first before building configs.'
    )]
    public function execute(
        string $classId = '',
        bool    $includeExamples = false,
        bool    $includeClassSchema = true
    ): CallToolResult {
        try {
            $context = [
                'schema' => $this->getSchema(),
                'transformation_operators' => $this->getOperators(),
                'data_target_types' => $this->getTargets(),
                'available_classes' => $this->getClasses(),
            ];

            if ($classId !== '' && $includeClassSchema) {
                $context['field_type_matrix'] = $this->getFieldTypeMatrix(
                    $classId
                );
            }

            if ($includeExamples) {
                $context['examples'] = $this->getExamples();
            }

            return new CallToolResult(
                [new TextContent(json_encode($context, JSON_PRETTY_PRINT))],
                isError: false
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to get configuration context',
                [
                    'classId' => $classId,
                    'exception' => $e
                ]
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

    private function getSchema(): array
    {
        return $this->configurationSchemaService->getCompleteSchema();
    }

    private function getOperators(): array
    {
        $schema = $this->configurationSchemaService
            ->getMappingConfigSchema();

        if (isset(
            $schema['items']['properties']['transformationPipeline']['availableOperators']
        )) {
            return $schema['items']['properties']['transformationPipeline']['availableOperators'];
        }

        $this->logger->warning(
            'No transformation operators found in schema'
        );

        return [];
    }

    private function getTargets(): array
    {
        $schema = $this->configurationSchemaService
            ->getMappingConfigSchema();

        if (isset(
            $schema['items']['properties']['dataTarget']['availableTargets']
        )) {
            return $schema['items']['properties']['dataTarget']['availableTargets'];
        }

        $this->logger->warning('No data targets found in schema');

        return [];
    }

    private function getClasses(): array
    {
        return $this->configurationSchemaService->getAvailableClasses();
    }

    private function getExamples(): array
    {
        $examplesPath = __DIR__ . '/../../../docs/examples';

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
                continue;
            }

            try {
                $config = \Symfony\Component\Yaml\Yaml::parse($content);
            } catch (\Exception $e) {
                continue;
            }

            $examples[] = [
                'name' => basename($file, '.yaml'),
                'title' => $config['general']['name'] ?? 'Unknown',
                'description' =>
                    $config['general']['description'] ?? '',
                'configuration' => $config
            ];
        }

        return $examples;
    }

    private function getFieldTypeMatrix(string $classId): array
    {
        return $this->configurationSchemaService->getFieldTypeMatrix(
            $classId
        );
    }
}
