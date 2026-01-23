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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Service;

use Mcp\Schema\ServerCapabilities;
use Mcp\Server;
use Mcp\Server\Session\Psr16StoreSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Builds and configures the MCP server for Data Importer tools.
 *
 * @internal
 */
final class McpServerBuilder
{
    private const SERVER_NAME = 'Pimcore Data Importer';

    private const SERVER_VERSION = '1.0.0';

    private const SERVER_DESCRIPTION =
        'Pimcore Data Importer MCP Server - Configuration and validation tools';

    private const SESSION_PREFIX = 'data_importer_mcp_session_';

    private const SESSION_TTL = 3600;

    private ?Server $server = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache
    ) {
    }

    public function buildServer(): Server
    {
        // Return cached server instance to preserve sessions
        if ($this->server !== null) {
            return $this->server;
        }

        $this->server = Server::builder()
            ->setServerInfo(
                self::SERVER_NAME,
                self::SERVER_VERSION,
                self::SERVER_DESCRIPTION
            )
            ->setCapabilities(new ServerCapabilities(
                tools: true,
                resources: false,
                prompts: false,
                logging: false
            ))
            ->setSession(
                sessionStore: new Psr16StoreSession(
                    $this->cache,
                    self::SESSION_PREFIX,
                    self::SESSION_TTL
                )
            )
            ->setLogger($this->logger)
            // PSR-11 container for dependency injection
            ->setContainer($this->container)
            // Attribute-based auto-discovery
            ->setDiscovery(
                dirname(__DIR__),
                ['Tool']
            )
            ->build();

        return $this->server;
    }
}
