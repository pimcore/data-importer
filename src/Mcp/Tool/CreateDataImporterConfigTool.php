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
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ConfigurationTypes;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotWriteableException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class CreateDataImporterConfigTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'create_import_config';

    public function __construct(
        private ConfigurationServiceInterface $configurationService,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Create Import Configuration',
        description: 'Create a new, empty Data Importer configuration. Fails if the name already '
            . 'exists, so check with list_import_configs first. Then call save_import_config to '
            . 'populate it. New configurations are inactive: set general.active to true in the '
            . 'configuration you save, or the import never runs.',
        annotations: new ToolAnnotations(
            readOnlyHint: false,
            destructiveHint: false,
            idempotentHint: false,
            openWorldHint: false
        )
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Unique name for the configuration, lowercase with hyphens, '
                . 'for example "csv-car-import".'
        )]
        string $name,
        #[Schema(
            type: 'string',
            description: 'Folder path for the configuration. Omit for the root.'
        )]
        ?string $path = null,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->configurationService->addConfiguration(
                $name,
                ConfigurationTypes::DATA_IMPORTER_DATA_OBJECT,
                $path ?? ''
            );
        } catch (ForbiddenException | NotWriteableException $e) {
            return $this->errorResult($e->getMessage(), self::CODE_PERMISSION_DENIED);
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['name' => $name]);
        }

        return $this->successResult([
            'created' => true,
            'name' => $name,
            'next' => 'Call save_import_config to populate it, with general.active set to true.',
        ]);
    }
}
