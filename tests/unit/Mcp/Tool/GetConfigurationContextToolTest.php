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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\GetConfigurationContextTool;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\DataImporterBundle\Validation\Schema\ConfigurationSchemaService;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class GetConfigurationContextToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string VALID_SECTIONS = 'classes, loaders, interpreters, resolver, operators, '
        . 'targets, field_type_matrix, operators_by_output, schema';

    private const string OPERATOR_POINTER = 'See the "operators" section of this tool.';

    private const string TARGET_POINTER = 'See the "targets" section of this tool.';

    /** @var array<string, mixed> */
    private const array OPERATORS = [
        'trim' => [
            'type' => 'trim',
            'description' => 'Trims whitespace',
            'settings' => ['mode' => ['type' => 'string', 'description' => 'Trimming mode']],
            'acceptedInputTypes' => ['default'],
            'outputTypes' => ['default'],
        ],
        'numeric' => [
            'type' => 'numeric',
            'description' => 'Casts to a number',
            'settings' => [],
            'acceptedInputTypes' => ['default'],
            'outputTypes' => ['numeric'],
        ],
    ];

    /** @var array<string, mixed> */
    private const array TARGETS = [
        'direct' => [
            'type' => 'direct',
            'description' => 'Writes to a field',
            'settings' => ['fieldName' => ['type' => 'string', 'description' => 'Target field']],
        ],
    ];

    /** @var array<string, mixed> */
    private const array LOCATION_TYPES = [
        'staticPath' => [
            'type' => 'staticPath',
            'description' => 'Puts every element in one folder',
            'settings' => ['path' => ['type' => 'string', 'description' => 'Folder path']],
        ],
    ];

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, schemaService: $this->unreachableSchemaService());

        $this->assertToolError($tool->execute(), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure.
        $securityService = $this->makeEmpty(SecurityServiceInterface::class, [
            'getCurrentUser' => static function (): never {
                throw new UserNotFoundException();
            },
        ]);

        $tool = new GetConfigurationContextTool(
            $this->unreachableSchemaService(),
            $securityService,
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError($tool->execute(), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testOmittedSectionsServeTheCheapDefaultsOnly(): void
    {
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        // The expensive catalogues stay opt in: the key set is the contract, not just its content.
        $this->assertSame(['classes', 'loaders', 'interpreters'], array_keys($payload));
        $this->assertSame(['6' => 'Car'], $payload['classes']);
    }

    public function testAnEmptySectionListIsTreatedAsOmitted(): void
    {
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute([]));

        $this->assertSame(['classes', 'loaders', 'interpreters'], array_keys($payload));
    }

    public function testLoaderTypesAreReadFromTheTopLevelAvailableTypes(): void
    {
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute(['loaders']));

        $this->assertSame(['loaders'], array_keys($payload));
        $this->assertSame(['push' => ['label' => 'Push'], 'asset' => ['label' => 'Asset']], $payload['loaders']);
    }

    public function testInterpreterTypesFallBackToTheNestedTypeProperty(): void
    {
        // The two schema builders nest availableTypes differently; the tool has to read both.
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute(['interpreters']));

        $this->assertSame(['csv' => ['label' => 'CSV']], $payload['interpreters']);
    }

    public function testATypeCatalogueThatIsNotAnArrayCollapsesToAnEmptyList(): void
    {
        $schemaService = $this->schemaService(['getLoaderConfigSchema' => ['availableTypes' => 'nope']]);

        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true, schemaService: $schemaService)->execute(['loaders'])
        );

        $this->assertSame([], $payload['loaders']);
    }

    public function testTheSecondLocationCatalogueIsReplacedByAPointerToTheFirst(): void
    {
        // Both location strategies choose from the same catalogue, and it is the largest part of
        // the section, so shipping it twice doubled the cost of the section for nothing.
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute(['resolver']));

        $properties = $payload['resolver']['properties'];

        $this->assertSame(
            'Same as createLocationStrategy.availableTypes.',
            $properties['locationUpdateStrategy']['availableTypes'],
        );
        $this->assertArrayHasKey('staticPath', $properties['createLocationStrategy']['availableTypes']);
    }

    public function testACatalogueThatDiffersBetweenTheLocationStrategiesIsKept(): void
    {
        $schemaService = $this->schemaService([
            'getResolverConfigSchema' => [
                'properties' => [
                    'createLocationStrategy' => ['availableTypes' => self::LOCATION_TYPES],
                    'locationUpdateStrategy' => ['availableTypes' => ['noChange' => ['description' => 'Stays']]],
                ],
            ],
        ]);

        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true, schemaService: $schemaService)->execute(['resolver'])
        );

        $this->assertSame(
            ['noChange' => ['description' => 'Stays']],
            $payload['resolver']['properties']['locationUpdateStrategy']['availableTypes'],
        );
    }

    public function testOperatorAndTargetCataloguesAreReadOutOfTheMappingSchema(): void
    {
        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true)->execute(['operators', 'targets'])
        );

        $this->assertSame(['operators', 'targets'], array_keys($payload));
        $this->assertSame(['trim', 'numeric'], array_keys($payload['operators']));
        $this->assertSame(['direct'], array_keys($payload['targets']));
    }

    public function testTheBriefCatalogueNamesEachSettingRatherThanDescribingIt(): void
    {
        // The default. Whether a type is the right one is answered by the description and the
        // setting names; the schema of each setting only matters once it is being written.
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute(['operators']));

        $this->assertSame(
            [
                'description' => 'Trims whitespace',
                'settings' => ['mode'],
                'acceptedInputTypes' => ['default'],
                'outputTypes' => ['default'],
            ],
            $payload['operators']['trim'],
        );
    }

    public function testTheFullCatalogueKeepsTheSettingSchemas(): void
    {
        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true)->execute(['operators'], null, 'full')
        );

        $this->assertSame(self::OPERATORS['trim'], $payload['operators']['trim']);
    }

    public function testAnUnknownDetailIsRejectedRatherThanSilentlyIgnored(): void
    {
        $result = $this->buildTool(allowed: true)->execute(['operators'], null, 'verbose');

        $this->assertToolError(
            $result,
            'Unknown detail "verbose". Valid values: brief, full.',
            'invalid_request'
        );
    }

    public function testOperatorsByOutputAnswersWhichOperatorProducesAType(): void
    {
        // The lookup four consecutive failed saves were groping for: the target field rejects
        // "default" and accepts "numeric", so which operator gets the pipeline there?
        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true)->execute(['operators_by_output'])
        );

        $this->assertSame(
            ['default' => ['trim'], 'numeric' => ['numeric']],
            $payload['operators_by_output'],
        );
    }

    public function testAMissingMappingSchemaPathCollapsesToAnEmptyList(): void
    {
        $schemaService = $this->schemaService(['getMappingConfigSchema' => ['items' => ['properties' => []]]]);

        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true, schemaService: $schemaService)->execute(['operators', 'targets'])
        );

        $this->assertSame([], $payload['operators']);
        $this->assertSame([], $payload['targets']);
    }

    public function testFieldTypeMatrixIsServedForAGivenClassId(): void
    {
        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true)->execute(['field_type_matrix'], 'CAR')
        );

        $this->assertSame(['field_type_matrix'], array_keys($payload));
        $this->assertSame(['default' => ['name']], $payload['field_type_matrix']);
    }

    /**
     * @dataProvider missingClassIdProvider
     */
    public function testFieldTypeMatrixWithoutAClassIdIsRejected(?string $classId): void
    {
        // Answering [] here was indistinguishable from a class with no fields and flipped the
        // response from an object to an array, so the agent could not tell it had to retry.
        $this->assertToolError(
            $this->buildTool(allowed: true)->execute(['field_type_matrix'], $classId),
            'The field_type_matrix section requires a classId.',
            'invalid_request'
        );
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function missingClassIdProvider(): array
    {
        return [
            'omitted' => [null],
            'empty string' => [''],
        ];
    }

    public function testTheSchemaSectionReplacesTheCataloguesItDuplicates(): void
    {
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute(['schema']));

        $mapping = $payload['schema']['mappingConfig']['items']['properties'];
        $this->assertSame(self::OPERATOR_POINTER, $mapping['transformationPipeline']['availableOperators']);
        $this->assertSame(self::TARGET_POINTER, $mapping['dataTarget']['availableTargets']);
        // Only the two duplicated catalogues are replaced; the rest of the schema is untouched.
        $this->assertSame(['type' => 'object'], $payload['schema']['general']);
        $this->assertSame(['type' => 'array'], $mapping['transformationPipeline']['items']);
    }

    public function testTheSchemaSectionSurvivesACompleteSchemaWithoutCatalogues(): void
    {
        $schemaService = $this->schemaService(['getCompleteSchema' => ['general' => ['type' => 'object']]]);

        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true, schemaService: $schemaService)->execute(['schema'])
        );

        $this->assertSame(['type' => 'object'], $payload['schema']['general']);
    }

    public function testAnUnknownSectionIsRejectedInsteadOfSilentlyServingTheDefaults(): void
    {
        // Dropping the unknown name used to return the full default payload as if nothing had
        // happened, so a typo cost thousands of tokens and produced no signal at all.
        $result = $this->buildTool(allowed: true)->execute(['classes', 'operatorz']);

        $this->assertToolError(
            $result,
            'Unknown section(s): operatorz. Valid sections: ' . self::VALID_SECTIONS . '.',
            'invalid_request'
        );
        $this->assertStringNotContainsString('Car', (string) $result->content[0]->text);
    }

    public function testEveryUnknownSectionIsNamedAtOnce(): void
    {
        $this->assertToolError(
            $this->buildTool(allowed: true)->execute(['nope', 'classes', 'alsoNope']),
            'Unknown section(s): nope, alsoNope. Valid sections: ' . self::VALID_SECTIONS . '.',
            'invalid_request'
        );
    }

    public function testANonStringSectionEntryIsRejectedRatherThanCoerced(): void
    {
        // A JSON array of the wrong element type is still a caller mistake, and must not read as
        // "no sections given", which would silently serve the defaults.
        $this->assertToolError(
            $this->buildTool(allowed: true)->execute([42]),
            'Unknown section(s): . Valid sections: ' . self::VALID_SECTIONS . '.',
            'invalid_request'
        );
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $schemaService = $this->schemaService([
            'getAvailableClasses' => static function (): never {
                throw new StubFailureException('class definition storage unreachable at 10.0.0.5:3306');
            },
        ]);

        $this->assertGenericInternalError(
            $this->buildTool(allowed: true, schemaService: $schemaService)->execute(),
            'get_import_config_context',
            '10.0.0.5'
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function schemaService(array $overrides = []): ConfigurationSchemaService
    {
        return $this->makeEmpty(ConfigurationSchemaService::class, $overrides + [
            'getAvailableClasses' => ['6' => 'Car'],
            'getLoaderConfigSchema' => [
                'availableTypes' => ['push' => ['label' => 'Push'], 'asset' => ['label' => 'Asset']],
            ],
            'getInterpreterConfigSchema' => [
                'properties' => ['type' => ['availableTypes' => ['csv' => ['label' => 'CSV']]]],
            ],
            'getResolverConfigSchema' => [
                'properties' => [
                    'loadingStrategy' => ['availableTypes' => ['id' => []]],
                    'createLocationStrategy' => ['availableTypes' => self::LOCATION_TYPES],
                    'locationUpdateStrategy' => ['availableTypes' => self::LOCATION_TYPES],
                ],
            ],
            'getMappingConfigSchema' => [
                'items' => [
                    'properties' => [
                        'transformationPipeline' => ['availableOperators' => self::OPERATORS],
                        'dataTarget' => ['availableTargets' => self::TARGETS],
                    ],
                ],
            ],
            'getFieldTypeMatrix' => ['default' => ['name']],
            'getCompleteSchema' => [
                'general' => ['type' => 'object'],
                'mappingConfig' => [
                    'items' => [
                        'properties' => [
                            'transformationPipeline' => [
                                'availableOperators' => ['trim' => ['label' => 'Trim']],
                                'items' => ['type' => 'array'],
                            ],
                            'dataTarget' => ['availableTargets' => ['direct' => ['label' => 'Direct']]],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function unreachableSchemaService(): ConfigurationSchemaService
    {
        return $this->makeEmpty(ConfigurationSchemaService::class, [
            'getAvailableClasses' => static function (): never {
                self::fail('The schema service must not be reached without the permission.');
            },
            'getCompleteSchema' => static function (): never {
                self::fail('The schema service must not be reached without the permission.');
            },
        ]);
    }

    private function buildTool(
        bool $allowed,
        ?ConfigurationSchemaService $schemaService = null,
    ): GetConfigurationContextTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new GetConfigurationContextTool(
            $schemaService ?? $this->schemaService(),
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
