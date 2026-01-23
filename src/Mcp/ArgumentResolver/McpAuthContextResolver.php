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

namespace Pimcore\Bundle\DataImporterBundle\Mcp\ArgumentResolver;

use Pimcore\Bundle\DataImporterBundle\Exception\AccessDeniedException;
use Pimcore\Bundle\DataImporterBundle\Mcp\Security\McpAuthContext;
use Pimcore\Bundle\DataImporterBundle\Mcp\Security\McpAuthenticationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Resolves McpAuthContext from request Authorization header.
 * Validates bearer token against configured tokens.
 *
 * @internal
 */
final readonly class McpAuthContextResolver implements ValueResolverInterface
{
    private const MSG_AUTH_HEADER_MISSING = 'Authorization header missing';

    private const MSG_INVALID_AUTH_FORMAT =
        'Invalid Authorization format. Expected: Bearer <token>';

    private const MSG_INVALID_TOKEN = 'Invalid bearer token';

    public function __construct(
        private McpAuthenticationService $authenticationService
    ) {
    }

    /**
     * @throws AccessDeniedException
     */
    public function resolve(
        Request $request,
        ArgumentMetadata $argument
    ): iterable {
        if ($argument->getType() !== McpAuthContext::class) {
            return [];
        }

        $authHeader = $request->headers->get('Authorization');
        if ($authHeader === null) {
            throw new AccessDeniedException(self::MSG_AUTH_HEADER_MISSING);
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new AccessDeniedException(self::MSG_INVALID_AUTH_FORMAT);
        }

        $bearerToken = substr($authHeader, 7);

        if (!$this->authenticationService->validateToken($bearerToken)) {
            throw new AccessDeniedException(self::MSG_INVALID_TOKEN);
        }

        yield new McpAuthContext($bearerToken);
    }
}
