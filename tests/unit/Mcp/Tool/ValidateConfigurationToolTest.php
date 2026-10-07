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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ValidateConfigurationTool;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\DataImporterBundle\Validation\ConfigurationValidationService;
use Pimcore\Bundle\DataImporterBundle\Validation\ValidationError;
use Pimcore\Bundle\DataImporterBundle\Validation\ValidationResult;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class ValidateConfigurationToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string JSON_BODY = '{"general": {"name": "csv-car-import"}, "mappingConfig": []}';

    private const string YAML_BODY = "general:\n    name: csv-car-import\nmappingConfig: []\n";

    /** @var array<string, mixed> */
    private const array PARSED = ['general' => ['name' => 'csv-car-import'], 'mappingConfig' => []];

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, validationService: $this->unreachableValidationService());

        $this->assertToolError(
            $tool->execute(self::JSON_BODY),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure.
        $tool = new ValidateConfigurationTool(
            $this->unreachableValidationService(),
            $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError(
            $tool->execute(self::JSON_BODY),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testAValidConfigurationAnswersWithNothingButTheVerdict(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            validationService: $this->validationService(new ValidationResult(true)),
        );

        $payload = $this->assertToolSuccess($tool->execute(self::JSON_BODY));

        // No errors key at all: a valid configuration must not read as "zero errors so far".
        $this->assertSame(['valid' => true], $payload);
    }

    public function testAnInvalidConfigurationIsAResultRatherThanAnError(): void
    {
        $result = new ValidationResult(false, [
            new ValidationError('mappingConfig[0].dataTarget', 'Unknown data target type `nope`'),
            new ValidationError('resolverConfig', 'dataObjectClassId is required'),
        ]);

        $tool = $this->buildTool(allowed: true, validationService: $this->validationService($result));

        // isError stays false: the agent is meant to read the errors and fix the configuration.
        $payload = $this->assertToolSuccess($tool->execute(self::JSON_BODY));

        $this->assertSame(['valid', 'errors'], array_keys($payload));
        $this->assertFalse($payload['valid']);
        $this->assertSame(
            [
                ['path' => 'mappingConfig[0].dataTarget', 'message' => 'Unknown data target type `nope`'],
                ['path' => 'resolverConfig', 'message' => 'dataObjectClassId is required'],
            ],
            $payload['errors'],
        );
    }

    public function testWarningsAreNotReported(): void
    {
        // Only errors are actionable for the agent, and the warning list is where the noise is.
        $result = new ValidationResult(
            false,
            [new ValidationError('general', 'name is required')],
            [new ValidationError('general.active', 'the import will not run')],
        );

        $tool = $this->buildTool(allowed: true, validationService: $this->validationService($result));

        $payload = $this->assertToolSuccess($tool->execute(self::JSON_BODY));
        $this->assertSame(['valid', 'errors'], array_keys($payload));
        $this->assertCount(1, $payload['errors']);
    }

    /**
     * @dataProvider bodyProvider
     */
    public function testTheConfigurationReachesTheValidatorAsAnArray(string $body, ?string $format): void
    {
        $captured = null;
        $validationService = $this->makeEmpty(ConfigurationValidationService::class, [
            'validateConfiguration' => static function (array $configuration) use (&$captured): ValidationResult {
                $captured = $configuration;

                return new ValidationResult(true);
            },
        ]);

        $tool = $this->buildTool(allowed: true, validationService: $validationService);

        $this->assertToolSuccess($tool->execute($body, $format));
        $this->assertSame(self::PARSED, $captured);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function bodyProvider(): array
    {
        return [
            'json auto-detected' => [self::JSON_BODY, null],
            'yaml auto-detected' => [self::YAML_BODY, null],
            'json named explicitly' => [self::JSON_BODY, 'json'],
            'yaml named explicitly' => [self::YAML_BODY, 'yaml'],
        ];
    }

    public function testAMalformedConfigurationNeverReachesTheValidator(): void
    {
        // A body that cannot be parsed is never handed on as a configuration, and the caller is
        // told what is wrong with its own payload rather than given a correlation id.
        $tool = $this->buildTool(allowed: true, validationService: $this->unreachableValidationService());

        $result = $tool->execute('{"general": ');

        $this->assertTrue($result->isError);
        $payload = $this->decodeToolResult($result);
        $this->assertSame('invalid_request', $payload['code']);
        $this->assertStringContainsString('not valid JSON', (string) $payload['error']);
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $validationService = $this->makeEmpty(ConfigurationValidationService::class, [
            'validateConfiguration' => static function (): never {
                throw new StubFailureException('class definition storage unreachable at db.internal:3306');
            },
        ]);

        $tool = $this->buildTool(allowed: true, validationService: $validationService);

        $this->assertGenericInternalError(
            $tool->execute(self::JSON_BODY),
            'validate_import_config',
            'db.internal'
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
                self::fail('The validation service must not be reached.');
            },
        ]);
    }

    private function buildTool(
        bool $allowed,
        ConfigurationValidationService $validationService,
    ): ValidateConfigurationTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new ValidateConfigurationTool(
            $validationService,
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
