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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\Security;

/**
 * Service for authenticating MCP requests against configured bearer tokens.
 *
 * @internal
 */
final readonly class McpAuthenticationService
{
    public function __construct(
        private array $allowedBearerTokens
    ) {
    }

    /**
     * Validate a bearer token against the configured allowed tokens.
     *
     * @param string $bearerToken The token from Authorization header
     *
     * @return bool True if token is valid
     */
    public function validateToken(string $bearerToken): bool
    {
        if (empty($bearerToken)) {
            return false;
        }

        return in_array($bearerToken, $this->allowedBearerTokens, true);
    }

    /**
     * Check if any bearer tokens are configured.
     *
     * @return bool
     */
    public function hasConfiguredTokens(): bool
    {
        return !empty($this->allowedBearerTokens);
    }
}
