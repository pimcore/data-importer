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
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataImporterBundle\Tool\LoadableAttributesInterface;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
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

    public function __construct(
        private LoadableAttributesInterface $loadableAttributes,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Get Class Fields For Loading',
        description: 'Fields a data object of this class can be looked up by: the attribute names '
            . 'accepted by the "attribute" loading strategy and by the "Load Data Object" operator. '
            . 'This is NOT the list of fields you can import into: for those call '
            . 'get_import_config_context with the field_type_matrix section. Includes the system '
            . 'columns id, key and path.',
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
            $fields = $this->loadableAttributes->listLoadableAttributes($classId);
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['classId' => $classId]);
        }

        if ($fields === []) {
            // An unknown class id used to be indistinguishable from a class with no
            // filterable fields, both returning an empty list on a successful call.
            return $this->notFoundResult(sprintf(
                'Class "%s" not found. Check the class id with the classes section of '
                . 'get_import_config_context.',
                $classId
            ));
        }

        return $this->successResult(['classId' => $classId, 'filterableFields' => $fields]);
    }
}
