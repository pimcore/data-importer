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
 * Authentication context for MCP server requests.
 * Contains the bearer token used for authentication.
 *
 * @internal
 */
final readonly class McpAuthContext
{
    public function __construct(
        private string $bearerToken
    ) {
    }

    public function getBearerToken(): string
    {
        return $this->bearerToken;
    }

    public function isAuthenticated(): bool
    {
        return !empty($this->bearerToken);
    }
}
