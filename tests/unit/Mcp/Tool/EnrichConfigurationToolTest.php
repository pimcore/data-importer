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
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Cleanup\CleanupStrategyFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\DataTargetInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\OperatorInterface;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\EnrichConfigurationTool;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Pimcore\Bundle\DataImporterBundle\Resolver\ResolverFactory;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * MappingConfigurationFactory and ImportProcessingService are final, so these tests wire the real
 * ones and inject the operator and data target blueprints instead. That is also the honest seam:
 * the transformation result type is exactly what the operator chain says it is.
 *
 * @internal
 */
final class EnrichConfigurationToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string NO_MAPPING_CONFIG = 'Configuration must have a mappingConfig array.';

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, operators: $this->unreachableOperators());

        $this->assertToolError(
            $tool->execute($this->fullConfiguration()),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure.
        $tool = $this->buildTool(
            allowed: true,
            operators: $this->unreachableOperators(),
            securityService: $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
        );

        $this->assertToolError(
            $tool->execute($this->fullConfiguration()),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testAFullConfigurationYieldsOneTypeEntryPerMappingItem(): void
    {
        $tool = $this->buildTool(allowed: true);

        $payload = $this->assertToolSuccess($tool->execute($this->fullConfiguration()));

        // Only the computed types come back: the caller already holds the configuration, and
        // echoing it back was the single largest response in the whole tool set.
        $this->assertSame(['types'], array_keys($payload));
        $this->assertSame(
            [
                ['index' => 0, 'label' => 'Name', 'transformationResultType' => 'default'],
                ['index' => 1, 'label' => 'Price', 'transformationResultType' => 'quantityValue'],
            ],
            $payload['types'],
        );
    }

    public function testTheNestedMappingItemsShapeWrittenByTheStudioFormIsAccepted(): void
    {
        $configuration = json_encode([
            'general' => ['name' => 'csv-car-import'],
            'mappingConfig' => [
                'mappingItems' => [
                    ['label' => 'Name', 'dataSourceIndex' => 0, 'dataTarget' => ['type' => 'direct']],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute($configuration));

        $this->assertSame(
            [['index' => 0, 'label' => 'Name', 'transformationResultType' => 'default']],
            $payload['types'],
        );
    }

    public function testMappingItemKeysAreReportedAsGivenSoTheAnswerCanBeMappedBack(): void
    {
        $configuration = json_encode([
            'general' => ['name' => 'csv-car-import'],
            'mappingConfig' => [
                'first' => ['label' => 'Name', 'dataSourceIndex' => 0, 'dataTarget' => ['type' => 'direct']],
            ],
        ], JSON_THROW_ON_ERROR);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute($configuration));

        $this->assertSame('first', $payload['types'][0]['index']);
    }

    public function testASingleMappingItemIsRecognisedAndReportedAtIndexZero(): void
    {
        $configuration = json_encode(
            ['label' => 'Price', 'dataSourceIndex' => 1, 'dataTarget' => ['type' => 'direct'],
                'transformationPipeline' => [['type' => 'quantityValue']]],
            JSON_THROW_ON_ERROR
        );

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute($configuration));

        $this->assertSame(
            [['index' => 0, 'label' => 'Price', 'transformationResultType' => 'quantityValue']],
            $payload['types'],
        );
    }

    public function testAFullConfigurationIsNeverMistakenForASingleMappingItem(): void
    {
        // The discriminator is the absence of general and mappingConfig, so a configuration whose
        // top level happens to carry a label must still be walked as a full configuration.
        $configuration = json_encode([
            'label' => 'looks like an item',
            'dataSourceIndex' => 0,
            'dataTarget' => ['type' => 'direct'],
            'general' => ['name' => 'csv-car-import'],
            'mappingConfig' => [
                ['label' => 'Name', 'dataSourceIndex' => 0, 'dataTarget' => ['type' => 'direct']],
            ],
        ], JSON_THROW_ON_ERROR);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute($configuration));

        $this->assertSame('Name', $payload['types'][0]['label']);
    }

    public function testYamlIsAcceptedJustLikeJson(): void
    {
        $configuration = "general:\n    name: csv-car-import\n"
            . "mappingConfig:\n"
            . "    -   label: Name\n        dataSourceIndex: 0\n        dataTarget:\n            type: direct\n";

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute($configuration));

        $this->assertSame(
            [['index' => 0, 'label' => 'Name', 'transformationResultType' => 'default']],
            $payload['types'],
        );
    }

    public function testTheWholePipelineIsEvaluatedInOrder(): void
    {
        $seen = [];
        $operators = [
            'trim' => $this->operator(static function (string $inputType, ?int $index) use (&$seen): string {
                $seen[] = [$index, $inputType];

                return 'default';
            }),
            'quantityValue' => $this->operator(static function (string $inputType, ?int $index) use (&$seen): string {
                $seen[] = [$index, $inputType];

                return 'quantityValue';
            }),
        ];

        $configuration = json_encode([
            'general' => ['name' => 'csv-car-import'],
            'mappingConfig' => [
                [
                    'label' => 'Price',
                    'dataSourceIndex' => 2,
                    'dataTarget' => ['type' => 'direct'],
                    'transformationPipeline' => [['type' => 'trim'], ['type' => 'quantityValue']],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $payload = $this->assertToolSuccess(
            $this->buildTool(allowed: true, operators: $operators)->execute($configuration)
        );

        $this->assertSame('quantityValue', $payload['types'][0]['transformationResultType']);
        $this->assertSame([[1, 'default'], [2, 'default']], $seen, 'Each operator sees its predecessor.');
    }

    public function testAMultiColumnDataSourceIndexStartsThePipelineAsAnArray(): void
    {
        $configuration = json_encode([
            'general' => ['name' => 'csv-car-import'],
            'mappingConfig' => [
                ['label' => 'Gallery', 'dataSourceIndex' => [3, 4], 'dataTarget' => ['type' => 'direct']],
            ],
        ], JSON_THROW_ON_ERROR);

        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute($configuration));

        $this->assertSame('array', $payload['types'][0]['transformationResultType']);
    }

    public function testTheConfigurationNameIsHandedToTheOperators(): void
    {
        $names = [];
        $operators = ['trim' => $this->operator(
            static fn (): string => 'default',
            static function (string $configName) use (&$names): void {
                $names[] = $configName;
            }
        )];

        $tool = $this->buildTool(allowed: true, operators: $operators);

        $this->assertToolSuccess($tool->execute($this->configurationWithTrim(['name' => 'csv-car-import'])));
        $this->assertToolSuccess($tool->execute($this->configurationWithTrim([])));

        // A configuration that has not been named yet is enriched under a placeholder rather than
        // being rejected: enrichment happens before the configuration is created.
        $this->assertSame(['csv-car-import', 'temp'], $names);
    }

    /**
     * @dataProvider missingMappingConfigProvider
     *
     * @param array<string, mixed> $configuration
     */
    public function testAConfigurationWithoutAMappingConfigArrayIsRejected(array $configuration): void
    {
        $tool = $this->buildTool(allowed: true, operators: $this->unreachableOperators());

        $this->assertToolError(
            $tool->execute((string) json_encode($configuration, JSON_THROW_ON_ERROR)),
            self::NO_MAPPING_CONFIG,
            'invalid_request'
        );
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function missingMappingConfigProvider(): array
    {
        return [
            'absent' => [['general' => ['name' => 'csv-car-import']]],
            'null' => [['general' => ['name' => 'csv-car-import'], 'mappingConfig' => null]],
            'a scalar' => [['general' => ['name' => 'csv-car-import'], 'mappingConfig' => 'nope']],
        ];
    }

    public function testANonObjectMappingItemIsRejectedNamingItsIndex(): void
    {
        $configuration = json_encode([
            'general' => ['name' => 'csv-car-import'],
            'mappingConfig' => [
                ['label' => 'Name', 'dataSourceIndex' => 0, 'dataTarget' => ['type' => 'direct']],
                'nope',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->assertToolError(
            $this->buildTool(allowed: true)->execute($configuration),
            'mappingConfig[1] must be an object.',
            'invalid_request'
        );
    }

    public function testAnUnknownOperatorTypeNamesTheOperatorItGotWrong(): void
    {
        // The factory composes its message from the caller's own operator name, so the tool
        // re-wraps it as the one type the shared handler forwards. Returning a correlation id
        // instead would tell the agent nothing about the typo it can fix.
        $configuration = json_encode([
            'general' => ['name' => 'csv-car-import'],
            'mappingConfig' => [
                [
                    'label' => 'Name',
                    'dataSourceIndex' => 0,
                    'dataTarget' => ['type' => 'direct'],
                    'transformationPipeline' => [['type' => 'trimm']],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->buildTool(allowed: true)->execute($configuration);

        $this->assertTrue($result->isError);
        $payload = $this->decodeToolResult($result);
        $this->assertSame('invalid_request', $payload['code']);
        $this->assertStringContainsString('trimm', (string) $payload['error']);
        $this->assertStringContainsString('mappingConfig[0]', (string) $payload['error']);
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $operators = ['trim' => $this->operator(static function (): never {
            throw new StubFailureException('operator registry unreachable at 10.0.0.5:3306');
        })];

        $tool = $this->buildTool(allowed: true, operators: $operators);

        $this->assertGenericInternalError(
            $tool->execute($this->configurationWithTrim(['name' => 'csv-car-import'])),
            'enrich_import_config',
            '10.0.0.5'
        );
    }

    private function fullConfiguration(): string
    {
        return (string) json_encode([
            'general' => ['name' => 'csv-car-import'],
            'mappingConfig' => [
                ['label' => 'Name', 'dataSourceIndex' => 0, 'dataTarget' => ['type' => 'direct']],
                [
                    'label' => 'Price',
                    'dataSourceIndex' => 1,
                    'dataTarget' => ['type' => 'direct'],
                    'transformationPipeline' => [['type' => 'quantityValue']],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $general
     */
    private function configurationWithTrim(array $general): string
    {
        return (string) json_encode([
            'general' => $general,
            'mappingConfig' => [
                [
                    'label' => 'Name',
                    'dataSourceIndex' => 0,
                    'dataTarget' => ['type' => 'direct'],
                    'transformationPipeline' => [['type' => 'trim']],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function operator(callable $evaluateReturnType, ?callable $setConfigName = null): OperatorInterface
    {
        return $this->makeEmpty(OperatorInterface::class, [
            'evaluateReturnType' => $evaluateReturnType,
            'setConfigName' => $setConfigName ?? static fn (): null => null,
        ]);
    }

    /**
     * @return array<string, OperatorInterface>
     */
    private function unreachableOperators(): array
    {
        return ['trim' => $this->makeEmpty(OperatorInterface::class, [
            'setSettings' => static function (): never {
                self::fail('The mapping configuration must not be built.');
            },
        ])];
    }

    /**
     * @param array<string, OperatorInterface>|null $operators
     */
    private function buildTool(
        bool $allowed,
        ?array $operators = null,
        ?SecurityServiceInterface $securityService = null,
    ): EnrichConfigurationTool {
        $operators ??= [
            'trim' => $this->operator(static fn (): string => 'default'),
            'quantityValue' => $this->operator(static fn (): string => 'quantityValue'),
        ];

        $factory = new MappingConfigurationFactory(
            new MappingConfiguration(),
            $operators,
            ['direct' => $this->makeEmpty(DataTargetInterface::class)],
        );

        $processingService = new ImportProcessingService(
            new QueueService(),
            $factory,
            new ResolverFactory(new Resolver(), [], [], [], []),
            new CleanupStrategyFactory([]),
            $this->makeEmpty(ApplicationLogger::class),
            new EventDispatcher(),
        );

        $securityService ??= $this->makeEmpty(SecurityServiceInterface::class, [
            'getCurrentUser' => $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]),
        ]);

        return new EnrichConfigurationTool(
            $factory,
            $processingService,
            $securityService,
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
