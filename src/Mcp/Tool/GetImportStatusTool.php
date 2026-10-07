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
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tool\ImportConfigurationRepositoryInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function sprintf;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Registered with the Pimcore Agent Bundle's MCP server when that bundle is installed, and
 * usable as a handler in a custom Mcp\Server. See doc/07_MCP_Tools.md.
 *
 * The read half of run_import_config: an import returns as soon as its queue is filled, so
 * without this the caller has no way to tell whether it finished.
 */
final readonly class GetImportStatusTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'get_import_status';

    public function __construct(
        private ImportServiceInterface $importService,
        private ImportConfigurationRepositoryInterface $configurations,
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'Get Import Status',
        description: 'Progress of the import queue for one Data Importer configuration. Returns '
            . 'isRunning, totalItems, processedItems and progress (0 to 1). isRunning is true '
            . 'while items are still queued, so poll this after run_import_config until it turns '
            . 'false. A configuration that has never run reports zero items rather than an error.',
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: false, openWorldHint: false)
    )]
    public function execute(
        #[Schema(
            type: 'string',
            description: 'Name of the configuration to report on.'
        )]
        string $name,
    ): CallToolResult {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            if ($this->configurations->findReadableByName($name) === null) {
                return $this->notFoundResult(
                    sprintf('Data Importer configuration "%s" not found.', $name)
                );
            }

            $progress = $this->importService->checkImportProgress($name);
        } catch (NotFoundHttpException $e) {
            return $this->notFoundResult($e->getMessage());
        } catch (ForbiddenException $e) {
            return $this->errorResult($e->getMessage(), self::CODE_PERMISSION_DENIED);
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME, ['name' => $name]);
        }

        return $this->successResult([
            'name' => $name,
            'isRunning' => $progress->getIsRunning(),
            'totalItems' => $progress->getTotalItems(),
            'processedItems' => $progress->getProcessedItems(),
            'progress' => $progress->getProgress(),
        ]);
    }
}
