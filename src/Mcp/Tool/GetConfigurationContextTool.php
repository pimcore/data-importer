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
        description: 'Get comprehensive configuration context in a single ' .
            'call for creating or modifying Data Importer configurations. ' .
            'ALWAYS RETURNS: (1) schema - complete JSON schema defining ' .
            'valid configuration structure with required fields, data ' .
            'types, and validation rules; (2) transformation_operators - ' .
            'list of available data transformation operators (trim, ' .
            'replace, split, numeric, boolean, etc.) with their settings ' .
            'schemas, accepted input types, and output types. Each ' .
            'operator includes acceptedInputTypes (array of data types ' .
            'the operator can process: default, numeric, boolean, array, ' .
            'dataObject, asset, etc.) and outputType (array of data types ' .
            'the operator produces after transformation). Use this type ' .
            'information to build valid transformation pipelines by ' .
            'chaining operators where output types match next operator\'s ' .
            'accepted input types; (3) data_target_types - available ' .
            'import targets (direct field, classification store, asset ' .
            'metadata) with required settings; (4) available_classes - ' .
            'list of Pimcore data object classes that can be used as ' .
            'import targets. OPTIONALLY RETURNS (when classId provided): ' .
            'field_type_matrix - matrix showing which class fields are ' .
            'compatible with which transformation result types (e.g., ' .
            'which fields accept numeric vs text vs asset data). ' .
            'OPTIONALLY RETURNS (when includeExamples=true): ' .
            'examples - working configuration samples demonstrating ' .
            'common import scenarios. Use this tool to gather all ' .
            'necessary information before generating or validating ' .
            'configurations. TRANSFORMATION PIPELINE VALIDATION: When ' .
            'building multi-step transformations, ensure each operator\'s ' .
            'outputType is compatible with the next operator\'s ' .
            'acceptedInputTypes. For example: source data (default) -> ' .
            'explode (outputs: array) -> trim on each element (accepts: ' .
            'default, outputs: default) -> numeric (accepts: default/ ' .
            'boolean, outputs: numeric) -> target field (accepts: numeric).'
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
