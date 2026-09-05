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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\GetConfigurationExamplesTool;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The examples this tool serves are the YAML files shipped in doc/examples, so these tests also
 * guard those fixtures: a broken or renamed example fails here rather than in an agent session.
 *
 * @internal
 */
final class GetConfigurationExamplesToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    public function testDeniedWhenMissingPermission(): void
    {
        $result = $this->buildTool(allowed: false)->execute();

        $this->assertToolError($result, self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure.
        $tool = new GetConfigurationExamplesTool(
            $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
            new McpToolErrorHandler(new NullLogger()),
            $this->failingLogger(),
        );

        $this->assertToolError($tool->execute(), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testEveryShippedExampleIsServedInFileNameOrder(): void
    {
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $this->assertSame(['examples'], array_keys($payload));
        $this->assertSame(
            ['01-basic-csv-import', '02-csv-relation-transformations', '03-json-more-transformations'],
            array_column($payload['examples'], 'name'),
        );
    }

    public function testAnExampleCarriesItsDescriptionSummaryAndTheWholeConfiguration(): void
    {
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());
        $example = $payload['examples'][0];

        $this->assertSame(
            ['name', 'description', 'summary', 'configuration'],
            array_keys($example),
        );
        $this->assertSame(
            'Import data from a CSV file with simplest mapping',
            $example['description'],
        );
        $this->assertSame(
            [
                'loaderType' => 'push',
                'interpreterType' => 'csv',
                'targetClass' => '6',
                'mappingCount' => 1,
                'usedOperators' => [],
            ],
            $example['summary'],
        );
        // The configuration is the copyable payload, so it has to be the complete document.
        $this->assertSame(
            [
                'general',
                'loaderConfig',
                'interpreterConfig',
                'resolverConfig',
                'processingConfig',
                'mappingConfig',
                'executionConfig',
                'permissions',
                'workspaces',
            ],
            array_keys($example['configuration']),
        );
    }

    public function testTheSummaryReportsEachDistinctOperatorOfThePipelinesOnce(): void
    {
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $summary = $payload['examples'][1]['summary'];
        $this->assertSame(['loadDataObject', 'importAsset', 'gallery'], $summary['usedOperators']);
        $this->assertSame(2, $summary['mappingCount']);
        $this->assertSame(34, $summary['targetClass'], 'The class id is served as written in the example.');
    }

    public function testTheJsonExampleIsSummarisedFromItsOwnLoaderAndInterpreter(): void
    {
        // Pins that every summary field is read from its own source: the third example is the only
        // one that is not a pushed CSV, so transposing loader and interpreter fails here.
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $summary = $payload['examples'][2]['summary'];
        $this->assertSame('asset', $summary['loaderType']);
        $this->assertSame('json', $summary['interpreterType']);
        $this->assertSame('PD', $summary['targetClass']);
        $this->assertSame(['trim', 'explode', 'quantityValue'], $summary['usedOperators']);
    }

    private function failingLogger(): LoggerInterface
    {
        return $this->makeEmpty(LoggerInterface::class, [
            'warning' => static function (): never {
                self::fail('No example may fail to read or parse.');
            },
        ]);
    }

    private function buildTool(bool $allowed): GetConfigurationExamplesTool
    {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new GetConfigurationExamplesTool(
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
            $this->failingLogger(),
        );
    }
}
