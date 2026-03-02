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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\Traits\ConfigurationParserTrait;
use Pimcore\Bundle\StudioBackendBundle\Mcp\McpToolInterface;
use Psr\Log\LoggerInterface;

/**
 * MCP tool to create a new Data Importer configuration.
 *
 * Delegates to the DataHub ConfigurationService for entry creation
 * (with permission and writeable checks), then saves the initial
 * configuration data via updateConfiguration.
 *
 * @internal
 */
final readonly class CreateDataImporterConfigTool implements McpToolInterface
{
    use ConfigurationParserTrait;

    private const CONFIG_TYPE = 'dataImporterDataObject';

    public function __construct(
        private ConfigurationServiceInterface $configurationService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'pimcore_dataimporter_create_configuration',
        description: 'Create a new Data Importer configuration in DataHub. Fails if name already '
            . 'exists (use save_configuration to update). Created inactive by default. Must include '
            . 'all sections: general, loaderConfig, interpreterConfig, resolverConfig, '
            . 'processingConfig, mappingConfig. Validate with validate_configuration first.'
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
            description: 'The full Data Importer configuration as '
                . 'JSON or YAML string. Must include general, '
                . 'loaderConfig, interpreterConfig, resolverConfig, '
                . 'processingConfig, and mappingConfig sections.'
        )]
        string $configuration,
        #[Schema(
            type: 'string',
            description: 'Format of the configuration: "json" or '
                . '"yaml". Auto-detects if not specified.'
        )]
        string $format = ''
    ): CallToolResult {
        try {
            $configArray = $this->parseConfiguration($configuration, $format);

            $configArray['general'] = $configArray['general'] ?? [];
            $configArray['general']['name'] = $name;
            $configArray['general']['type'] = self::CONFIG_TYPE;

            // Create the DataHub entry (checks permissions + writeable + uniqueness)
            $this->configurationService->addConfiguration($name, self::CONFIG_TYPE, '');

            // Save the initial configuration data
            $modificationDate = $this->configurationService->updateConfiguration(
                $name,
                $configArray,
                0
            );

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'success' => true,
                                'name' => $name,
                                'modificationDate' => $modificationDate,
                                'message' => 'Data Importer configuration "'
                                    . $name . '" created successfully.',
                                'hint' => 'The configuration is inactive by default. '
                                    . 'Set general.active to true and save again to activate it.'
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
