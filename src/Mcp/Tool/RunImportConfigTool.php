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
use Pimcore\Bundle\DataImporterBundle\Exception\QueueNotEmptyException;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tool\ImportConfigurationRepositoryInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotWriteableException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function sprintf;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/07_MCP_Tools.md.
 *
 * Runs through {@see ImportServiceInterface} rather than the preparation service underneath it.
 * That service is what the Studio button calls, so starting an import from here enforces the
 * same per configuration update right and behaves the same way; calling the preparation service
 * directly would skip the permission check entirely, since it prepares with permissions ignored.
 */
final readonly class RunImportConfigTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'run_import_config';

    public function __construct(
        private ImportServiceInterface $importService,
        private ImportConfigurationRepositoryInterface $configurations,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Run Import Configuration',
        description: 'Start the import described by a Data Importer configuration. This reads the '
            . 'configured source, interprets it and queues the records; a background worker then '
            . 'creates and updates the data objects, so the call returns as soon as the queue is '
            . 'filled rather than when the import has finished. Poll get_import_status for '
            . 'progress. Requires the update right on the configuration, the same right the '
            . 'Studio button requires. Validate a configuration before running it: this writes '
            . 'real data objects.',
        // Creates and updates data objects from the source, and reaches whatever the loader
        // points at, so neither read-only nor closed-world.
        annotations: new ToolAnnotations(
            readOnlyHint: false,
            destructiveHint: true,
            idempotentHint: false,
            openWorldHint: true
        )
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Name of the configuration to run. Use list_import_configs to discover names.'
        )]
        string $name,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            // The Studio service resolves the name against every Data Hub configuration, so the
            // type check has to happen here: without it a GraphQL configuration could be handed
            // to the importer by name.
            if ($this->configurations->findReadableByName($name) === null) {
                return $this->notFoundResult(
                    sprintf('Data Importer configuration "%s" not found.', $name)
                );
            }

            $started = $this->importService->startImport($name)->isSuccess();
        } catch (NotFoundHttpException $e) {
            return $this->notFoundResult($e->getMessage());
        } catch (ForbiddenException | NotWriteableException $e) {
            return $this->errorResult($e->getMessage(), self::CODE_PERMISSION_DENIED);
        } catch (QueueNotEmptyException $e) {
            // Actionable rather than internal: an import is already in flight for this
            // configuration, and the caller can wait for it or cancel it.
            return $this->errorResult($e->getMessage(), self::CODE_INVALID_REQUEST);
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['name' => $name]);
        }

        return $this->successResult([
            'started' => $started,
            'name' => $name,
            'next' => $started
                ? 'Records are queued. Poll get_import_status until isRunning is false.'
                : 'The import did not start. Check the configuration and the application log.',
        ]);
    }
}
