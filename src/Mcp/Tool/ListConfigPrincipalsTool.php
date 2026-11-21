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
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Pimcore\Model\User\Listing as UserListing;
use Pimcore\Model\User\Role\Listing as RoleListing;
use Throwable;
use function usort;

/**
 * The roles and users a configuration's permissions can name.
 *
 * A permission entry is matched by name, not by id — see Configuration::isAllowed, which keys
 * its permission sets on `name` — so an agent that has to write one needs the spellings this
 * installation actually has. Names only: what a permission entry holds, and nothing else about
 * the people behind them.
 *
 * @internal
 */
final readonly class ListConfigPrincipalsTool
{
    use DataImporterToolHelper;

    private const string TOOL_NAME = 'list_config_principals';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private McpToolErrorHandlerInterface $errorHandler,
    ) {
    }

    #[McpTool(
        name: self::TOOL_NAME,
        title: 'List Roles and Users for Configuration Permissions',
        description: 'List the role and user names this installation has, for the permissions '
            . 'section of an import configuration. A permission entry is matched by name, so use '
            . 'these spellings verbatim — never invent a name or an id. Call this before proposing '
            . 'any change to permissions.',
        annotations: new ToolAnnotations(
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        )
    )]
    public function execute(): CallToolResult
    {
        $denied = $this->denyIfNotAllowed($this->securityService);
        if ($denied !== null) {
            return $denied;
        }

        try {
            $roles = [];
            foreach ((new RoleListing())->load() as $role) {
                $name = $role->getName();
                if ($name !== null && $name !== '') {
                    $roles[] = ['id' => $role->getId(), 'name' => $name];
                }
            }

            $users = [];
            foreach ((new UserListing())->load() as $user) {
                // the system user is nobody's to grant, and an admin already passes every check
                if (!$user instanceof User || $user->getId() <= 0 || $user->isAdmin()) {
                    continue;
                }

                $name = $user->getName();
                if ($name !== null && $name !== '') {
                    $users[] = ['id' => $user->getId(), 'name' => $name];
                }
            }

            usort($roles, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);
            usort($users, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);
        } catch (Throwable $e) {
            return $this->handledError($this->errorHandler, $e, self::TOOL_NAME);
        }

        return $this->successResult(['roles' => $roles, 'users' => $users]);
    }
}
