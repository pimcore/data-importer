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

use function is_array;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function sprintf;
use Throwable;

/**
 * One import configuration as it is stored — the document propose_import_config expects back
 * with changes applied.
 *
 * @internal
 */
final readonly class GetImportConfigTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'get_import_config';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Get Import Configuration',
        description: 'Read one Data Importer configuration in full: general, loaderConfig, '
            . 'interpreterConfig, resolverConfig, processingConfig, mappingConfig and executionConfig. '
            . 'Always read a configuration before proposing changes to it — propose_import_config '
            . 'takes the complete document back, not a patch.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(
        #[Schema(type: 'string', description: 'Name of the configuration, as list_import_configs reports it.')]
        string $name,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $configuration = Configuration::getByName($name);
            $document = $configuration?->getConfiguration();
            if (!is_array($document)) {
                return $this->notFoundResult(sprintf(
                    'No import configuration named "%s". List them with list_import_configs.',
                    $name,
                ));
            }
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['name' => $name]);
        }

        return $this->successResult(['name' => $name, 'configuration' => $document]);
    }
}
