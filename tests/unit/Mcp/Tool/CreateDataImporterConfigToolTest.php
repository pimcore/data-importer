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
use Pimcore\Bundle\DataHubBundle\Service\Studio\ConfigurationServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\CreateDataImporterConfigTool;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ConfigurationTypes;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class CreateDataImporterConfigToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CONFIG_NAME = 'csv-car-import';

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, configurationService: $this->unreachableService());

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure, and above
        // all it must not create anything.
        $tool = new CreateDataImporterConfigTool(
            $this->unreachableService(),
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

    public function testTheConfigurationIsCreatedUnderThisBundlesDataHubTypeAtTheRoot(): void
    {
        $captured = [];
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'addConfiguration' => static function (string $name, string $type, string $path) use (&$captured): string {
                $captured = [$name, $type, $path];

                return $name;
            },
        ]);

        $tool = $this->buildTool(allowed: true, configurationService: $configurationService);

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME));

        // An omitted path means the root, and the type is what makes it a Data Importer config
        // rather than any other Data Hub adapter.
        $this->assertSame(
            [self::CONFIG_NAME, ConfigurationTypes::DATA_IMPORTER_DATA_OBJECT, ''],
            $captured,
        );
        $this->assertSame(['created', 'name', 'next'], array_keys($payload));
        $this->assertTrue($payload['created']);
        $this->assertSame(self::CONFIG_NAME, $payload['name']);
        // New configurations are inactive, so the answer has to say what is still missing.
        $this->assertSame(
            'Call save_import_config to populate it, with general.active set to true.',
            $payload['next'],
        );
    }

    public function testAGivenPathIsPassedThrough(): void
    {
        $captured = [];
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'addConfiguration' => static function (string $name, string $type, string $path) use (&$captured): string {
                $captured = [$name, $type, $path];

                return $name;
            },
        ]);

        $tool = $this->buildTool(allowed: true, configurationService: $configurationService);

        $this->assertToolSuccess($tool->execute(self::CONFIG_NAME, 'imports/cars'));
        $this->assertSame('imports/cars', $captured[2]);
    }

    public function testADataHubRefusalIsReportedAsAPermissionDenialWithItsOwnMessage(): void
    {
        // ForbiddenException is composed by the Data Hub service for the caller, and it is the one
        // failure here an agent can act on, so it is type-caught rather than genericised.
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'addConfiguration' => static function (): never {
                throw new ForbiddenException('You are not allowed to create configurations.');
            },
        ]);

        $tool = $this->buildTool(allowed: true, configurationService: $configurationService);

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME),
            'You are not allowed to create configurations.',
            'permission_denied'
        );
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        // A duplicate name arrives here as a plain exception from the Data Hub service.
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'addConfiguration' => static function (): never {
                throw new StubFailureException('config storage unreachable at db.internal:3306');
            },
        ]);

        $tool = $this->buildTool(allowed: true, configurationService: $configurationService);

        $this->assertGenericInternalError(
            $tool->execute(self::CONFIG_NAME),
            'create_import_config',
            'db.internal'
        );
    }

    private function unreachableService(): ConfigurationServiceInterface
    {
        return $this->makeEmpty(ConfigurationServiceInterface::class, [
            'addConfiguration' => static function (): never {
                self::fail('Nothing may be created without the permission.');
            },
        ]);
    }

    private function buildTool(
        bool $allowed,
        ConfigurationServiceInterface $configurationService,
    ): CreateDataImporterConfigTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new CreateDataImporterConfigTool(
            $configurationService,
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
