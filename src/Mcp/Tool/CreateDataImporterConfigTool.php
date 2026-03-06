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
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Mcp\McpToolInterface;
use Psr\Log\LoggerInterface;

/**
 * MCP tool to create a new Data Importer configuration.
 *
 * Delegates to the DataHub ConfigurationService::addConfiguration
 * (matching the AddController pattern) which handles permission checks,
 * writeable validation, and uniqueness. Use save_configuration to
 * populate the created entry with actual configuration data.
 *
 * @internal
 */
final readonly class CreateDataImporterConfigTool implements McpToolInterface
{
    private const CONFIG_TYPE = 'dataImporterDataObject';

    public function __construct(
        private ConfigurationServiceInterface $configurationService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'create_configuration',
        description: 'Create a new empty Data Importer configuration entry in DataHub. '
            . 'Fails if name already exists. After creation, use save_configuration '
            . 'to populate it with the actual configuration data.'
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Unique name for the configuration. '
                . 'Must not already exist in DataHub. '
                . 'Use lowercase with hyphens (e.g. "csv-car-import").'
        )]
        string $name,
        #[Schema(
            type: 'string',
            description: 'Configuration path in DataHub. '
                . 'Leave empty for the default location.'
        )]
        string $path = ''
    ): CallToolResult {
        try {
            $this->configurationService->addConfiguration(
                $name,
                self::CONFIG_TYPE,
                $path
            );

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'success' => true,
                                'name' => $name,
                                'type' => self::CONFIG_TYPE,
                                'message' => 'Data Importer configuration "'
                                    . $name . '" created successfully.',
                                'hint' => 'Use save_configuration to populate '
                                    . 'it with the actual configuration data.'
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: false
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to create Data Importer configuration',
                ['name' => $name, 'exception' => $e->getMessage()]
            );

            return new CallToolResult(
                [new TextContent(json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT))],
                isError: true
            );
        }
    }
}
