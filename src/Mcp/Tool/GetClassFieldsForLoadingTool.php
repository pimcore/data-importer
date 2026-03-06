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
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Pimcore\Bundle\StudioBackendBundle\Mcp\McpToolInterface;
use Psr\Log\LoggerInterface;

/**
 * Get filterable fields for a specific Pimcore class.
 *
 * Returns fields that can be used for data loading operations
 * (Load Data Object operator) - includes fields accepting default,
 * numeric, and calculated transformation types.
 *
 * @internal
 */
final readonly class GetClassFieldsForLoadingTool implements McpToolInterface
{
    public function __construct(
        private ConfigurationSchemaService $configurationSchemaService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'get_class_fields_for_loading',
        description: 'Get fields of a class usable as filter criteria in the "Load Data Object" '
            . 'relation-loading operator. Returns sorted field names accepting default, numeric, '
            . 'or calculated types. Needed when configuring relation imports.'
    )]
    public function execute(string $classId): CallToolResult
    {
        try {
            $matrix = $this->configurationSchemaService
                ->getFieldTypeMatrix($classId);

            $filterableFields = [];
            $relevantTypes = [
                TransformationDataTypeService::DEFAULT_TYPE,
                TransformationDataTypeService::NUMERIC,
                TransformationDataTypeService::CALCULATED,
            ];

            foreach ($relevantTypes as $type) {
                foreach (($matrix[$type] ?? []) as $field) {
                    if (!in_array($field, $filterableFields, true)) {
                        $filterableFields[] = $field;
                    }
                }
            }

            sort($filterableFields);

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'classId' => $classId,
                                'filterable_fields' => $filterableFields,
                                'count' => count($filterableFields),
                                'description' => 'Fields that can be used ' .
                                    'for filtering in Load Data Object ' .
                                    'operations (default, numeric, and ' .
                                    'calculated types)'
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: false
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to get filterable fields',
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
                                'type' => get_class($e),
                                'classId' => $classId,
                                'hint' => 'Use list_available_classes ' .
                                    'to see valid class IDs'
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: true
            );
        }
    }
}
