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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Mcp\Tool;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ListImportConfigsTool;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\DataHubConfigurationFixtureTrait;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ConfigurationTypes;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;

/**
 * The tool reads through the static Data Hub Configuration model rather than an injected service,
 * so these tests stand a container in front of it that serves the configurations from memory. See
 * {@see DataHubConfigurationFixtureTrait} for what that costs and why nothing here touches a
 * database.
 *
 * @internal
 */
final class ListImportConfigsToolTest extends Unit
{
    use DataHubConfigurationFixtureTrait;
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CONFIG_NAME = 'csv-car-import';

    private const string SECOND_CONFIG_NAME = 'json-part-import';

    protected function _after(): void
    {
        $this->restoreDataHubConfigurations();
    }

    public function testDeniedWhenMissingPermission(): void
    {
        $this->expectNoDataHubConfigurationAccess();

        $this->assertToolError(
            $this->buildTool(allowed: false)->execute(),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure.
        $this->expectNoDataHubConfigurationAccess();

        $tool = new ListImportConfigsTool(
            $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError($tool->execute(), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testEachConfigurationIsDescribedByWhatMattersBeforeReadingIt(): void
    {
        $this->giveDataHubConfigurations([
            self::CONFIG_NAME => $this->importerConfiguration(
                self::CONFIG_NAME,
                group: 'catalog',
                active: true,
                classId: '6',
            ),
        ]);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $this->assertSame(['configurations'], array_keys($payload));
        $this->assertSame(
            [[
                'name' => self::CONFIG_NAME,
                'group' => 'catalog',
                'active' => true,
                'targetClassId' => '6',
                'modificationDate' => 1769026708,
            ]],
            $payload['configurations'],
        );
    }

    public function testConfigurationsOfOtherDataHubAdaptersAreNotListed(): void
    {
        // The Data Hub configuration list is shared by every adapter, so the type filter is what
        // keeps a GraphQL endpoint out of an answer the agent will feed to save_import_config.
        $this->giveDataHubConfigurations([
            self::CONFIG_NAME => $this->importerConfiguration(self::CONFIG_NAME),
            'graphql-cars' => $this->importerConfiguration('graphql-cars', type: 'dataHubGraphQL'),
        ]);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $this->assertSame([self::CONFIG_NAME], array_column($payload['configurations'], 'name'));
    }

    public function testConfigurationsTheUserMayNotReadAreOmitted(): void
    {
        // Read rights are granted per configuration in Data Hub, so listing has to apply that
        // second gate on top of the bundle-wide permission the tool already checked.
        $this->giveDataHubConfigurations(
            [
                self::CONFIG_NAME => $this->importerConfiguration(self::CONFIG_NAME) + [
                    'permissions' => ['user' => [['name' => 'editor', 'read' => true]]],
                ],
                self::SECOND_CONFIG_NAME => $this->importerConfiguration(self::SECOND_CONFIG_NAME) + [
                    'permissions' => ['user' => [['name' => 'editor', 'read' => false]]],
                ],
            ],
            currentUser: $this->reader(),
        );

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $this->assertSame([self::CONFIG_NAME], array_column($payload['configurations'], 'name'));
    }

    public function testAConfigurationWithoutAnActiveFlagIsReportedAsInactive(): void
    {
        // An import that never runs is the single most common surprise here, so the flag has to be
        // false rather than missing when the stored configuration does not carry it.
        $stored = $this->importerConfiguration(self::CONFIG_NAME);
        unset($stored['general']['active'], $stored['resolverConfig']);
        $this->giveDataHubConfigurations([self::CONFIG_NAME => $stored]);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $this->assertFalse($payload['configurations'][0]['active']);
        $this->assertNull($payload['configurations'][0]['targetClassId']);
    }

    public function testAnEmptyStoreIsAnEmptyListRatherThanAnError(): void
    {
        $this->giveDataHubConfigurations([]);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $this->assertSame(['configurations' => []], $payload);
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $this->breakDataHubConfigurationAccess('config storage unreachable at 10.0.0.5:3306');

        $this->assertGenericInternalError(
            $this->buildTool(allowed: true)->execute(),
            'list_import_configs',
            '10.0.0.5'
        );
    }

    /**
     * Pimcore\Model\User is final, so this is a real one: named, not an admin and holding no
     * permissions of its own, which is what the per-configuration grid is evaluated against.
     */
    private function reader(): User
    {
        $user = new User();
        $user->setName('editor');
        $user->setAdmin(false);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function importerConfiguration(
        string $name,
        string $type = ConfigurationTypes::DATA_IMPORTER_DATA_OBJECT,
        string $group = '',
        bool $active = false,
        string $classId = '6',
    ): array {
        return [
            'general' => [
                'name' => $name,
                'type' => $type,
                'path' => '',
                'group' => $group,
                'active' => $active,
                'modificationDate' => 1769026708,
                'createDate' => 1769026497,
            ],
            'resolverConfig' => ['dataObjectClassId' => $classId],
        ];
    }

    private function buildTool(bool $allowed): ListImportConfigsTool
    {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new ListImportConfigsTool(
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
