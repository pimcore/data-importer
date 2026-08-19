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
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ConfigurationTypes;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class ListImportConfigsTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'list_import_configs';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'List Import Configurations',
        description: 'List the Data Importer configurations the current user may read, with '
            . 'their target class and whether they are active. Call this before '
            . 'create_import_config, which fails on an existing name, and before '
            . 'save_import_config, which fails on a missing one.',
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(): CallToolResult
    {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $configs = [];
            foreach (Configuration::getList() as $configuration) {
                if ($configuration->getType() !== ConfigurationTypes::DATA_IMPORTER_DATA_OBJECT) {
                    continue;
                }

                if (!$configuration->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ)) {
                    continue;
                }

                $configs[] = $this->describe($configuration);
            }
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME);
        }

        return $this->successResult(['configurations' => $configs]);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Configuration $configuration): array
    {
        $config = $configuration->getConfiguration();
        $general = $config['general'] ?? [];
        $resolver = $config['resolverConfig'] ?? [];

        return [
            'name' => $configuration->getName(),
            'group' => $configuration->getGroup(),
            'active' => (bool) ($general['active'] ?? false),
            'targetClassId' => $resolver['dataObjectClassId'] ?? null,
            'modificationDate' => $configuration->getModificationDate(),
        ];
    }
}
