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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\GetImportConfigTool;
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
final class GetImportConfigToolTest extends Unit
{
    use DataHubConfigurationFixtureTrait;
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CONFIG_NAME = 'csv-car-import';

    protected function _after(): void
    {
        $this->restoreDataHubConfigurations();
    }

    public function testDeniedWhenMissingPermission(): void
    {
        $this->expectNoDataHubConfigurationAccess();

        $tool = $this->buildTool(allowed: false);

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure.
        $this->expectNoDataHubConfigurationAccess();

        $tool = new GetImportConfigTool(
            $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testTheStoredConfigurationIsReturnedWholeUnderTheRequestedName(): void
    {
        $stored = $this->importerConfiguration(self::CONFIG_NAME) + [
            'loaderConfig' => ['type' => 'push'],
            'mappingConfig' => [['label' => 'Name', 'dataSourceIndex' => 0]],
        ];
        $this->giveDataHubConfigurations([self::CONFIG_NAME => $stored]);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute(self::CONFIG_NAME));

        $this->assertSame(['name', 'modificationDate', 'configuration'], array_keys($payload));
        $this->assertSame(self::CONFIG_NAME, $payload['name']);
        // The modification date is what save_import_config needs for optimistic locking.
        $this->assertSame(1769026708, $payload['modificationDate']);
        $this->assertSame($stored['loaderConfig'], $payload['configuration']['loaderConfig']);
        $this->assertSame($stored['mappingConfig'], $payload['configuration']['mappingConfig']);
        // Data Hub stamps the stored general block with whether this instance may write it back.
        $this->assertSame(
            $stored['general'] + ['writeable' => false],
            $payload['configuration']['general'],
        );
    }

    public function testAnUnknownNameIsReportedAsNotFound(): void
    {
        $this->giveDataHubConfigurations([self::CONFIG_NAME => $this->importerConfiguration(self::CONFIG_NAME)]);

        $this->assertToolError(
            $this->buildTool(allowed: true)->execute('typo-import'),
            'Data Importer configuration "typo-import" not found.',
            'not_found'
        );
    }

    public function testAConfigurationOfAnotherDataHubAdapterIsReportedAsNotFound(): void
    {
        // The name exists, but it belongs to a different Data Hub adapter. Serving it would hand
        // the agent a configuration it cannot validate, enrich or save with these tools.
        $this->giveDataHubConfigurations([
            'graphql-cars' => $this->importerConfiguration('graphql-cars', type: 'dataHubGraphQL'),
        ]);

        $this->assertToolError(
            $this->buildTool(allowed: true)->execute('graphql-cars'),
            'Data Importer configuration "graphql-cars" not found.',
            'not_found'
        );
    }

    public function testAUserWithoutReadRightsOnTheConfigurationIsDenied(): void
    {
        // The bundle-wide permission is not the per-configuration one: Data Hub grants read per
        // configuration, and this tool has to honour that second gate too.
        $this->giveDataHubConfigurations(
            [self::CONFIG_NAME => $this->importerConfiguration(self::CONFIG_NAME)],
            currentUser: $this->readerWithoutRights(),
        );

        $this->assertToolError(
            $this->buildTool(allowed: true)->execute(self::CONFIG_NAME),
            'You are not allowed to read configuration "csv-car-import".',
            'permission_denied'
        );
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $this->breakDataHubConfigurationAccess('config storage unreachable at 10.0.0.5:3306');

        $this->assertGenericInternalError(
            $this->buildTool(allowed: true)->execute(self::CONFIG_NAME),
            'get_import_config',
            '10.0.0.5'
        );
    }

    /**
     * Pimcore\Model\User is final, so this is a real one: no roles and no permissions, which is
     * what an editor who was never granted the Data Hub adapter permission looks like.
     */
    private function readerWithoutRights(): User
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
    ): array {
        return [
            'general' => [
                'name' => $name,
                'type' => $type,
                'path' => '',
                'group' => '',
                'active' => true,
                'modificationDate' => 1769026708,
                'createDate' => 1769026497,
            ],
        ];
    }

    private function buildTool(bool $allowed): GetImportConfigTool
    {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new GetImportConfigTool(
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
