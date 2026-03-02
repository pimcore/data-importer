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
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Pimcore\Bundle\StudioBackendBundle\Mcp\McpToolInterface;
use Psr\Log\LoggerInterface;

/**
 * Lists all available Pimcore Data Object classes that can be used as
 * import targets in Data Importer configurations.
 *
 * @internal
 */
final readonly class ListAvailableClassesTool implements McpToolInterface
{
    public function __construct(
        private ConfigurationSchemaService $configurationSchemaService,
        private LoggerInterface $logger
    ) {
    }

    #[McpTool(
        name: 'pimcore_dataimporter_list_available_classes',
        description: 'List Pimcore Data Object classes available as import targets. '
            . 'Returns class names and count. Use these class IDs in resolverConfig.dataObjectClassId.'
    )]
    public function execute(): CallToolResult
    {
        try {
            $classes = $this->listAvailableClasses();

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'classes' => $classes,
                                'count' => count($classes),
                                'description' => 'Available Data Object classes'
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: false
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to list available classes',
                ['exception' => $e]
            );

            return new CallToolResult(
                [
                    new TextContent(
                        json_encode(
                            [
                                'error' => $e->getMessage(),
                                'type' => get_class($e)
                            ],
                            JSON_PRETTY_PRINT
                        )
                    )
                ],
                isError: true
            );
        }
    }

    private function listAvailableClasses(): array
    {
        return $this->configurationSchemaService->getAvailableClasses();
    }

}
