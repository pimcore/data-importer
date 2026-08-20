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

use function array_map;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\Traits\ConfigurationParserTrait;
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;
use Pimcore\Bundle\DataImporterBundle\Validation\ValidationError;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class ValidateConfigurationTool
{
    use ConfigurationParserTrait;
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'validate_import_config';

    public function __construct(
        private ConfigurationValidationService $validationService,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Validate Import Configuration',
        description: 'Validate a Data Importer configuration before saving it. Returns '
            . '{valid: true} or {valid: false, errors: [{path, message}]}. The result type of '
            . 'every mapping item is computed from its transformationPipeline, so a missing or '
            . 'stale transformationResultType in the document changes nothing here. Accepts JSON '
            . 'or YAML, auto-detected. In YAML, every settings block must be a nested structure, '
            . 'never a JSON string.',
        // Pure function over the supplied configuration: nothing is stored.
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'The configuration as a JSON or YAML string.'
        )]
        string $configuration,
        #[Schema(
            type: 'string',
            description: 'Format of the configuration. Omit to auto-detect.',
            enum: ['json', 'yaml'],
        )]
        ?string $format = null,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $result = $this->validationService->validateConfiguration(
                $this->parseConfiguration($configuration, $format ?? '')
            );
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME);
        }

        if ($result->isValid()) {
            return $this->successResult(['valid' => true]);
        }

        return $this->successResult([
            'valid' => false,
            'errors' => array_map(
                static fn (ValidationError $error): array => [
                    'path' => $error->getPath(),
                    'message' => $error->getMessage(),
                ],
                $result->getErrors()
            ),
        ]);
    }
}
