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
 * MCP tool to retrieve configuration context in focused sections.
 *
 * Returns only the requested sections to keep responses compact
 * and avoid overwhelming the LLM context window.
 *
 * @internal
 */
final readonly class GetConfigurationContextTool implements McpToolInterface
{
    private const SECTION_OPERATORS = 'operators';
    private const SECTION_TARGETS = 'targets';
    private const SECTION_CLASSES = 'classes';
    private const SECTION_FIELD_MATRIX = 'field_type_matrix';
    private const SECTION_SCHEMA = 'schema';

    private const VALID_SECTIONS = [
        self::SECTION_OPERATORS,
        self::SECTION_TARGETS,
        self::SECTION_CLASSES,
        self::SECTION_FIELD_MATRIX,
        self::SECTION_SCHEMA,
    ];

    private const DEFAULT_SECTIONS = [
        self::SECTION_OPERATORS,
        self::SECTION_TARGETS,
        self::SECTION_CLASSES,
    ];

    public function __construct(
        private ConfigurationSchemaService $configurationSchemaService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'get_configuration_context',
        description: 'Get context needed to build Data Importer configurations. '
            . 'Use the "include" parameter to request specific sections (comma-separated): '
            . '"operators" (transformation operators with acceptedInputTypes and outputTypes '
            . '— chain operators where outputTypes of one match acceptedInputTypes of the next), '
            . '"targets" (data target types: direct, manyToManyRelation, classificationstore), '
            . '"classes" (available data object classes for import targets), '
            . '"field_type_matrix" (requires classId — shows which fields accept which '
            . 'transformation result types, e.g. "numeric" fields, "dataObject" fields, '
            . '"gallery" fields), '
            . '"schema" (full JSON schema for config validation — large, only request when needed). '
            . 'Default sections when include is empty: operators, targets, classes.'
    )]
    public function execute(
        string $classId = '',
        string $include = '',
        bool $includeExamples = false
    ): CallToolResult {
        try {
            $sections = $this->parseSections($include);
            $context = [];

            if (in_array(self::SECTION_OPERATORS, $sections, true)) {
                $context['transformation_operators'] =
                    $this->getOperators();
            }

            if (in_array(self::SECTION_TARGETS, $sections, true)) {
                $context['data_target_types'] = $this->getTargets();
            }

            if (in_array(self::SECTION_CLASSES, $sections, true)) {
                $context['available_classes'] = $this->getClasses();
            }

            if (
                in_array(self::SECTION_FIELD_MATRIX, $sections, true)
                && $classId !== ''
            ) {
                $context['field_type_matrix'] =
                    $this->getFieldTypeMatrix($classId);
            }

            if (in_array(self::SECTION_SCHEMA, $sections, true)) {
                $context['schema'] = $this->getSchema();
            }

            if ($includeExamples) {
                $context['examples'] = $this->getExamples();
            }

            return new CallToolResult(
                [new TextContent(
                    json_encode($context, JSON_PRETTY_PRINT)
                )],
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

    /**
     * @return string[]
     */
    private function parseSections(string $include): array
    {
        if ($include === '') {
            return self::DEFAULT_SECTIONS;
        }

        $requested = array_map(
            'trim',
            explode(',', $include)
        );

        $valid = array_filter(
            $requested,
            static fn (string $s): bool => in_array(
                $s,
                self::VALID_SECTIONS,
                true
            )
        );

        return $valid !== [] ? array_values($valid) : self::DEFAULT_SECTIONS;
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
            $schema['items']['properties']
                ['transformationPipeline']['availableOperators']
        )) {
            return $schema['items']['properties']
                ['transformationPipeline']['availableOperators'];
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
            $schema['items']['properties']
                ['dataTarget']['availableTargets']
        )) {
            return $schema['items']['properties']
                ['dataTarget']['availableTargets'];
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
