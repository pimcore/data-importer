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

use function in_array;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function sort;
use function sprintf;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class GetClassFieldsForLoadingTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'get_class_fields_for_loading';

    /** @var list<string> */
    private const array RELEVANT_TYPES = [
        TransformationDataTypeService::DEFAULT_TYPE,
        TransformationDataTypeService::NUMERIC,
        TransformationDataTypeService::CALCULATED,
    ];

    public function __construct(
        private ConfigurationSchemaService $configurationSchemaService,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Get Class Fields For Loading',
        description: 'Fields usable as filter criteria in the "Load Data Object" relation-loading '
            . 'operator for a class. This is NOT the list of fields you can import into: for those '
            . 'call get_import_config_context with the field_type_matrix section. Returns the field '
            . 'names that accept the default, numeric or calculated result types.',
        // Pure lookup over the class definition.
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Data object class id or class name, for example "CAR" or "Car".'
        )]
        string $classId,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $matrix = $this->configurationSchemaService->getFieldTypeMatrix($classId);

            $fields = [];
            foreach (self::RELEVANT_TYPES as $type) {
                foreach (($matrix[$type] ?? []) as $field) {
                    if (!in_array($field, $fields, true)) {
                        $fields[] = $field;
                    }
                }
            }
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['classId' => $classId]);
        }

        if ($fields === []) {
            // An unknown class id used to be indistinguishable from a class with no
            // filterable fields, both returning an empty list on a successful call.
            return $this->notFoundResult(sprintf(
                'No loadable fields for class "%s". Check the class id with the classes section '
                . 'of get_import_config_context.',
                $classId
            ));
        }

        sort($fields);

        return $this->successResult(['classId' => $classId, 'filterableFields' => $fields]);
    }
}
