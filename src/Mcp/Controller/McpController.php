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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Controller;

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Pimcore\Bundle\DataImporterBundle\Exception\AccessDeniedException;
use Pimcore\Bundle\DataImporterBundle\Mcp\Security\McpAuthContext;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * MCP server endpoint controller for Data Importer.
 *
 * AUTHENTICATION:
 * This endpoint is automatically authenticated via the McpAuthContext
 * parameter. When this parameter is type-hinted, Symfony's argument
 * resolver system invokes the McpAuthContextResolver which:
 *   1. Extracts Bearer token from Authorization header
 *   2. Validates it against configured bearer tokens
 *   3. Injects McpAuthContext into request
 *   4. Throws AccessDeniedException if auth fails (returns 401)
 *
 * @internal
 */
final readonly class McpController
{
    public function __construct(
        private Server $server,
        private HttpMessageFactoryInterface $httpMessageFactory,
        private HttpFoundationFactoryInterface $httpFoundationFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory
    ) {
    }

    /**
     * @throws AccessDeniedException When Bearer token is missing or invalid
     */
    #[Route(
        path: '/dataimporter-mcp',
        name: 'pimcore_dataimporter_mcp',
        methods: ['POST', 'GET']
    )]
    public function handle(
        Request $request,
        McpAuthContext $authContext
    ): Response {
        $transport = new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory
        );

        return $this->httpFoundationFactory->createResponse(
            $this->server->run($transport)
        );
    }
}
