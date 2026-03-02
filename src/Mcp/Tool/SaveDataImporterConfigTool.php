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
 * MCP tool to update an existing Data Importer configuration.
 *
 * Delegates to the DataHub ConfigurationService which handles permission
 * checks, writeable validation, conflict detection, and dehydration.
 *
 * @internal
 */
final readonly class SaveDataImporterConfigTool implements McpToolInterface
{
    use ConfigurationParserTrait;

    public function __construct(
        private ConfigurationServiceInterface $configurationService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'pimcore_dataimporter_save_configuration',
        description: 'Update an existing Data Importer configuration by name. Replaces the full '
            . 'configuration. Fails if name does not exist (use create_configuration for new ones). '
            . 'Validate with validate_configuration first.'
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Name of the existing configuration to update.'
        )]
        string $name,
        #[Schema(
            type: 'string',
            description: 'The full Data Importer configuration as '
                . 'JSON or YAML string. Must include all sections: '
                . 'general, loaderConfig, interpreterConfig, '
                . 'resolverConfig, processingConfig, mappingConfig.'
        )]
        string $configuration,
        #[Schema(
            type: 'string',
            description: 'Format: "json" or "yaml". Auto-detects '
                . 'if not specified.'
        )]
        string $format = '',
        #[Schema(
            type: 'integer',
            description: 'Modification date (Unix timestamp) from a '
                . 'previous get or create response. Used for conflict '
                . 'detection. Pass 0 to skip conflict checking.'
        )]
        int $modificationDate = 0
    ): CallToolResult {
        try {
            $configArray = $this->parseConfiguration($configuration, $format);

            $configArray['general'] = $configArray['general'] ?? [];
            $configArray['general']['name'] = $name;

            $newModificationDate = $this->configurationService->updateConfiguration(
                $name,
                $configArray,
                $modificationDate
            );

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'success' => true,
                                'name' => $name,
                                'modificationDate' => $newModificationDate,
                                'message' => 'Configuration "' . $name . '" saved successfully.'
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: false
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to save Data Importer configuration',
                ['name' => $name, 'exception' => $e->getMessage()]
            );

            return new CallToolResult(
                [new TextContent(json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT))],
                isError: true
            );
        }
    }

}
