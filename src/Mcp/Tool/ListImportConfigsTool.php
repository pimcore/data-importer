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
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Throwable;

/**
 * The import configurations this installation has, so an agent can name one before reading it.
 *
 * @internal
 */
final readonly class ListImportConfigsTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'list_import_configs';

    /** the adapter type this bundle owns; the Data Hub holds other kinds too */
    private const string CONFIG_TYPE = 'dataImporterDataObject';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'List Import Configurations',
        description: 'List the Data Importer configurations, with their group and whether they are '
            . 'active. Use this to find the exact name to pass to get_import_config.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(): CallToolResult
    {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $configurations = [];
            foreach (Configuration::getList() as $configuration) {
                if ($configuration->getType() !== self::CONFIG_TYPE) {
                    continue;
                }

                $general = $configuration->getConfiguration()['general'] ?? [];
                $configurations[] = [
                    'name' => $configuration->getName(),
                    'group' => $general['group'] ?? '',
                    'description' => $general['description'] ?? '',
                    'active' => (bool) ($general['active'] ?? false),
                ];
            }
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME);
        }

        return $this->successResult(['configurations' => $configurations]);
    }
}
