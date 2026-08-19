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

use const JSON_INVALID_UTF8_SUBSTITUTE;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Exception\InvalidMcpToolArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use function sprintf;
use Throwable;

/**
 * Shared plumbing for the Data Importer MCP tools: the explicit in-tool permission check
 * and the JSON success/error envelopes.
 *
 * MCP tools bypass the Symfony `#[IsGranted]` / `kernel.exception` pipeline the Studio
 * controllers rely on, so every tool has to gate itself against the same permission those
 * controllers require.
 *
 * @internal
 */
trait DataImporterToolHelper
{
    private const string CODE_INVALID_REQUEST = 'invalid_request';

    private const string CODE_INTERNAL_ERROR = 'internal_error';

    private const string CODE_NOT_FOUND = 'not_found';

    private const string CODE_PERMISSION_DENIED = 'permission_denied';

    /**
     * Returns null when the current user may configure imports, otherwise a ready-to-return
     * error envelope. Never throws: an unauthenticated user, an unresolvable one and a failing
     * permission lookup are all a denial, so this can run before the caller opens its own try
     * block without an exception escaping the tool as a bare JSON-RPC error.
     */
    private function denyIfNotAllowed(SecurityServiceInterface $securityService): ?CallToolResult
    {
        try {
            $allowed = $securityService->getCurrentUser()
                ->isAllowed(PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG);
        } catch (Throwable) {
            $allowed = false;
        }

        if (!$allowed) {
            return $this->errorResult(
                sprintf('Missing permission %s', PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG),
                self::CODE_PERMISSION_DENIED
            );
        }

        return null;
    }

    /**
     * Terminal `catch (Throwable)` of every tool here: routes the exception through the shared
     * handler and wraps whatever it releases in this bundle's envelope.
     *
     * The handler decides disclosure and this decides presentation, which is why it returns a
     * message rather than a result: only InvalidMcpToolArgumentException comes back verbatim,
     * everything else becomes a generic sentence plus a correlation id, with the exception
     * logged in full.
     *
     * @param array<string, mixed> $context additional structured logging context
     */
    private function handledError(
        McpToolErrorHandlerInterface $errorHandler,
        Throwable $exception,
        string $toolName,
        array $context = []
    ): CallToolResult {
        return $this->errorResult(
            $errorHandler->handle($exception, $toolName, $context),
            $exception instanceof InvalidMcpToolArgumentException
                ? self::CODE_INVALID_REQUEST
                : self::CODE_INTERNAL_ERROR,
        );
    }

    /**
     * The one error an agent can act on by re-reading the name or id it was given.
     */
    private function notFoundResult(string $message): CallToolResult
    {
        return $this->errorResult($message, self::CODE_NOT_FOUND);
    }

    private function errorResult(string $message, string $code = self::CODE_INVALID_REQUEST): CallToolResult
    {
        return new CallToolResult(
            [new TextContent($this->encode(['error' => $message, 'code' => $code]))],
            isError: true
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function successResult(array $payload): CallToolResult
    {
        return new CallToolResult([new TextContent($this->encode($payload))], isError: false);
    }

    /**
     * Compact on purpose: the payload is read by a model, and pretty printing was 42 to 61
     * percent of every response here. JSON_INVALID_UTF8_SUBSTITUTE guarantees a string even
     * when a class label or log line carries invalid UTF-8, which would otherwise collapse the
     * envelope to an empty result that reads as success.
     *
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        return (string) json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
