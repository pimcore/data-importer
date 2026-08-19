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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\SaveDataImporterConfigTool;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;
use Pimcore\Bundle\DataImporterBundle\Validation\ValidationError;
use Pimcore\Bundle\DataImporterBundle\Validation\ValidationResult;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class SaveDataImporterConfigToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CONFIG_NAME = 'csv-car-import';

    private const string JSON_BODY = '{"general": {"active": true}, "mappingConfig": []}';

    private const string YAML_BODY = "general:\n    active: true\nmappingConfig: []\n";

    private const int MODIFICATION_DATE = 1769026708;

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(
            allowed: false,
            configurationService: $this->unreachableService(),
            validationService: $this->unreachableValidationService(),
        );

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME, self::JSON_BODY),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure, and above
        // all it must not overwrite anything.
        $tool = new SaveDataImporterConfigTool(
            $this->unreachableService(),
            $this->unreachableValidationService(),
            $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME, self::JSON_BODY),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testAValidConfigurationIsWrittenAndTheNewModificationDateReported(): void
    {
        $captured = [];
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'updateConfiguration' => static function (
                string $name,
                array $configuration,
                int $clientModificationDate
            ) use (&$captured): int {
                $captured = [$name, $configuration, $clientModificationDate];

                return self::MODIFICATION_DATE;
            },
        ]);

        $tool = $this->buildTool(
            allowed: true,
            configurationService: $configurationService,
            validationService: $this->validationService(new ValidationResult(true)),
        );

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME, self::JSON_BODY));

        $this->assertSame(['saved', 'name', 'modificationDate'], array_keys($payload));
        $this->assertTrue($payload['saved']);
        $this->assertSame(self::CONFIG_NAME, $payload['name']);
        $this->assertSame(self::MODIFICATION_DATE, $payload['modificationDate']);

        $this->assertSame(self::CONFIG_NAME, $captured[0]);
        $this->assertSame(
            ['general' => ['active' => true, 'name' => self::CONFIG_NAME], 'mappingConfig' => []],
            $captured[1],
        );
        $this->assertGreaterThan(0, $captured[2], 'The write is stamped with the current time.');
    }

    public function testTheNameArgumentWinsOverTheNameInsideTheBody(): void
    {
        // Otherwise a copied configuration would be written under the name it was copied from,
        // which is the name the caller asked to keep untouched.
        $captured = null;
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'updateConfiguration' => static function (string $name, array $configuration) use (&$captured): int {
                $captured = $configuration;

                return self::MODIFICATION_DATE;
            },
        ]);

        $tool = $this->buildTool(
            allowed: true,
            configurationService: $configurationService,
            validationService: $this->validationService(new ValidationResult(true)),
        );

        $this->assertToolSuccess($tool->execute(
            self::CONFIG_NAME,
            '{"general": {"name": "some-other-config"}, "mappingConfig": []}'
        ));

        $this->assertSame(self::CONFIG_NAME, $captured['general']['name']);
    }

    public function testYamlIsAcceptedJustLikeJson(): void
    {
        $captured = null;
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'updateConfiguration' => static function (string $name, array $configuration) use (&$captured): int {
                $captured = $configuration;

                return self::MODIFICATION_DATE;
            },
        ]);

        $tool = $this->buildTool(
            allowed: true,
            configurationService: $configurationService,
            validationService: $this->validationService(new ValidationResult(true)),
        );

        $this->assertToolSuccess($tool->execute(self::CONFIG_NAME, self::YAML_BODY, 'yaml'));
        $this->assertSame(
            ['general' => ['active' => true, 'name' => self::CONFIG_NAME], 'mappingConfig' => []],
            $captured,
        );
    }

    public function testAnInvalidConfigurationIsRefusedAsASuccessfulResultAndNothingIsWritten(): void
    {
        // Deliberately not an error envelope: a rejected configuration is a result the agent can
        // act on, and validate_import_config reports the same shape the same way. Regressing this
        // to isError would make the agent treat its own fixable mistake as a tool failure.
        $result = new ValidationResult(false, [
            new ValidationError('mappingConfig[0].dataTarget', 'Unknown data target type `nope`'),
        ]);

        $tool = $this->buildTool(
            allowed: true,
            configurationService: $this->unreachableService(),
            validationService: $this->validationService($result),
        );

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME, self::JSON_BODY));

        $this->assertSame(['saved', 'valid', 'errors'], array_keys($payload));
        $this->assertFalse($payload['saved']);
        $this->assertFalse($payload['valid']);
        $this->assertSame(
            [['path' => 'mappingConfig[0].dataTarget', 'message' => 'Unknown data target type `nope`']],
            $payload['errors'],
        );
    }

    public function testTheValidatorSeesTheConfigurationThatWouldBeWritten(): void
    {
        // The name is forced before validation, so a configuration is never rejected for a name
        // the tool was about to overwrite anyway.
        $captured = null;
        $validationService = $this->makeEmpty(ConfigurationValidationService::class, [
            'validateConfiguration' => static function (array $configuration) use (&$captured): ValidationResult {
                $captured = $configuration;

                return new ValidationResult(false);
            },
        ]);

        $tool = $this->buildTool(
            allowed: true,
            configurationService: $this->unreachableService(),
            validationService: $validationService,
        );

        $this->assertToolSuccess($tool->execute(self::CONFIG_NAME, '{"general": {}, "mappingConfig": []}'));
        $this->assertSame(self::CONFIG_NAME, $captured['general']['name']);
    }

    public function testADataHubRefusalIsReportedAsAPermissionDenialWithItsOwnMessage(): void
    {
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'updateConfiguration' => static function (): never {
                throw new ForbiddenException('You are not allowed to update configuration "csv-car-import".');
            },
        ]);

        $tool = $this->buildTool(
            allowed: true,
            configurationService: $configurationService,
            validationService: $this->validationService(new ValidationResult(true)),
        );

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME, self::JSON_BODY),
            'You are not allowed to update configuration "csv-car-import".',
            'permission_denied'
        );
    }

    public function testAMalformedConfigurationIsNeitherValidatedNorWritten(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            configurationService: $this->unreachableService(),
            validationService: $this->unreachableValidationService(),
        );

        $result = $tool->execute(self::CONFIG_NAME, '{"general": ');

        $this->assertTrue($result->isError);
        $payload = $this->decodeToolResult($result);
        $this->assertSame('invalid_request', $payload['code']);
        $this->assertStringContainsString('not valid JSON', (string) $payload['error']);
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        // A missing configuration name arrives here as a plain exception from the Data Hub service.
        $configurationService = $this->makeEmpty(ConfigurationServiceInterface::class, [
            'updateConfiguration' => static function (): never {
                throw new StubFailureException('config storage unreachable at 10.0.0.5:3306');
            },
        ]);

        $tool = $this->buildTool(
            allowed: true,
            configurationService: $configurationService,
            validationService: $this->validationService(new ValidationResult(true)),
        );

        $this->assertGenericInternalError(
            $tool->execute(self::CONFIG_NAME, self::JSON_BODY),
            'save_import_config',
            '10.0.0.5'
        );
    }

    private function validationService(ValidationResult $result): ConfigurationValidationService
    {
        return $this->makeEmpty(ConfigurationValidationService::class, ['validateConfiguration' => $result]);
    }

    private function unreachableValidationService(): ConfigurationValidationService
    {
        return $this->makeEmpty(ConfigurationValidationService::class, [
            'validateConfiguration' => static function (): never {
                self::fail('Nothing may be validated here.');
            },
        ]);
    }

    private function unreachableService(): ConfigurationServiceInterface
    {
        return $this->makeEmpty(ConfigurationServiceInterface::class, [
            'updateConfiguration' => static function (): never {
                self::fail('Nothing may be written here.');
            },
        ]);
    }

    private function buildTool(
        bool $allowed,
        ConfigurationServiceInterface $configurationService,
        ConfigurationValidationService $validationService,
    ): SaveDataImporterConfigTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new SaveDataImporterConfigTool(
            $configurationService,
            $validationService,
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
