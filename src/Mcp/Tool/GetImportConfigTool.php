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
use Pimcore\Bundle\DataImporterBundle\Tool\ImportConfigurationRepositoryInterface;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function sprintf;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class GetImportConfigTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'get_import_config';

    public function __construct(
        private ImportConfigurationRepositoryInterface $configurations,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Get Import Configuration',
        description: 'Read one Data Importer configuration by name, so it can be modified and '
            . 'written back with save_import_config. Use list_import_configs to discover names.',
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(
        #[Schema(type: 'string', description: 'Name of the configuration to read.')]
        string $name,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $configuration = $this->configurations->findReadableByName($name);
            if ($configuration === null) {
                return $this->notFoundResult(
                    sprintf('Data Importer configuration "%s" not found.', $name)
                );
            }

            $config = $configuration->getConfiguration();
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['name' => $name]);
        }

        return $this->successResult([
            'name' => $name,
            'modificationDate' => $configuration->getModificationDate(),
            'configuration' => $config,
        ]);
    }
}
