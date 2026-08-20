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
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\Traits\ConfigurationParserTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ConfigurationTypes;
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;
use Pimcore\Bundle\DataImporterBundle\Validation\ValidationError;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotWriteableException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Throwable;
use function time;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/08_MCP_Tools.md.
 */
final readonly class SaveDataImporterConfigTool
{
    use ConfigurationParserTrait;
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'save_import_config';

    public function __construct(
        private ConfigurationServiceInterface $configurationService,
        private ConfigurationValidationService $validationService,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Save Import Configuration',
        description: 'Replace an existing Data Importer configuration by name. Run '
            . 'enrich_import_config and then validate_import_config first; this tool validates '
            . 'again and refuses to save an invalid configuration. Fails if the name does not '
            . 'exist, so create it with create_import_config. Set general.active to true or the '
            . 'import never runs.',
        annotations: new ToolAnnotations(
            readOnlyHint: false,
            destructiveHint: true,
            idempotentHint: true,
            openWorldHint: false
        )
    )]
    public function execute(
        #[Schema(type: 'string', description: 'Name of the existing configuration to replace.')]
        string $name,
        #[Schema(
            type: 'string',
            description: 'The full configuration as JSON or YAML, including the general, '
                . 'loaderConfig, interpreterConfig, resolverConfig, processingConfig and '
                . 'mappingConfig sections.'
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
            $configArray = $this->parseConfiguration($configuration, $format ?? '');
            $configArray['general']['name'] = $name;
            // The only value the schema accepts, so it is the tool's to set rather than
            // something the caller has to know and repeat.
            $configArray['general']['type'] = ConfigurationTypes::DATA_IMPORTER_DATA_OBJECT;

            $validationResult = $this->validationService->validateConfiguration($configArray);
            if (!$validationResult->isValid()) {
                // Deliberately not isError: a rejected configuration is a result the agent can
                // act on, and validate_import_config reports the same shape the same way.
                return $this->successResult([
                    'saved' => false,
                    'valid' => false,
                    'errors' => array_map(
                        static fn (ValidationError $error): array => [
                            'path' => $error->getPath(),
                            'message' => $error->getMessage(),
                        ],
                        $validationResult->getErrors()
                    ),
                ]);
            }

            $modificationDate = $this->configurationService->updateConfiguration(
                $name,
                $configArray,
                time()
            );
        } catch (ForbiddenException | NotWriteableException $e) {
            return $this->errorResult($e->getMessage(), self::CODE_PERMISSION_DENIED);
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['name' => $name]);
        }

        return $this->successResult([
            'saved' => true,
            'name' => $name,
            'modificationDate' => $modificationDate,
        ]);
    }
}
