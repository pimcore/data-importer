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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Mcp;

use Codeception\Test\Unit;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ListConfigPrincipalsTool;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandlerInterface;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Pimcore\Model\User\AbstractUser;
use RuntimeException;

class ListConfigPrincipalsToolTest extends Unit
{
    /** @var list<AbstractUser> */
    private array $created = [];

    protected function _after(): void
    {
        foreach ($this->created as $principal) {
            $principal->delete();
        }
        $this->created = [];
    }

    public function testAnUnresolvableUserGetsNoNames(): void
    {
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willThrowException(new RuntimeException('no session'));

        $result = $this->tool($security)->execute();

        static::assertTrue($result->isError);
        static::assertSame('permission_denied', $this->payload($result)['code']);
    }

    public function testRolesAndGrantableUsersAreListedByName(): void
    {
        $this->created[] = User\Role::create(['name' => 'import-reviewers', 'parentId' => 0]);
        $this->created[] = User\Role::create(['name' => 'import-editors', 'parentId' => 0]);
        $this->created[] = User::create(['name' => 'import-editor', 'parentId' => 0]);
        $this->created[] = User::create(['name' => 'import-admin', 'parentId' => 0, 'admin' => true]);

        $admin = new User();
        $admin->setAdmin(true);
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($admin);

        $payload = $this->payload($this->tool($security)->execute());
        $roles = array_column($payload['roles'], 'name');
        $users = array_column($payload['users'], 'name');

        static::assertContains('import-editors', $roles);
        static::assertContains('import-reviewers', $roles);
        static::assertContains('import-editor', $users);
        // an admin passes every permission check already
        static::assertNotContains('import-admin', $users);
        $sorted = $roles;
        sort($sorted);
        static::assertSame($sorted, $roles);
    }

    private function tool(SecurityServiceInterface $security): ListConfigPrincipalsTool
    {
        $errors = $this->createStub(McpToolErrorHandlerInterface::class);
        $errors->method('handle')->willReturn('Something went wrong.');

        return new ListConfigPrincipalsTool($security, $errors);
    }

    /** @return array<string, mixed> */
    private function payload(CallToolResult $result): array
    {
        $content = $result->content[0];
        static::assertInstanceOf(TextContent::class, $content);

        return json_decode($content->text, true, flags: JSON_THROW_ON_ERROR);
    }
}
