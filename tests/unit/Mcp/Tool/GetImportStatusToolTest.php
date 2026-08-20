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
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\GetImportStatusTool;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportProgressResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Tool\ImportConfigurationRepositoryInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;

/**
 * The read half of run_import_config. It reports the queue, so the cases worth pinning are the
 * ones an agent polls on: a finished queue has to be distinguishable from one that never ran.
 *
 * @internal
 */
final class GetImportStatusToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CONFIG_NAME = 'csv-car-import';

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, importService: $this->unreachableImportService());

        $this->assertToolError($tool->execute(self::CONFIG_NAME), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        $tool = new GetImportStatusTool(
            $this->unreachableImportService(),
            $this->repositoryServing(null),
            $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError($tool->execute(self::CONFIG_NAME), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testANameThatIsNotAReadableImportConfigurationIsNotReportedOn(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->unreachableImportService(),
            configurations: $this->repositoryServing(null),
        );

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME),
            'Data Importer configuration "csv-car-import" not found.',
            'not_found'
        );
    }

    public function testAQueueStillDrainingIsReportedAsRunningWithItsCounts(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->importServiceReporting(true, 150, 40, 0.27),
        );

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME));

        $this->assertSame(
            [
                'name' => self::CONFIG_NAME,
                'isRunning' => true,
                'totalItems' => 150,
                'processedItems' => 40,
                'progress' => 0.27,
            ],
            $payload,
        );
    }

    public function testAFinishedImportIsReportedAsNotRunning(): void
    {
        // What the agent polls for: this is the answer that ends the loop.
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->importServiceReporting(false, 150, 150, 1.0),
        );

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME));

        $this->assertFalse($payload['isRunning']);
        $this->assertSame(150, $payload['processedItems']);
    }

    public function testAConfigurationThatNeverRanReportsZeroRatherThanFailing(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->importServiceReporting(false, 0, 0, 0.0),
        );

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME));

        $this->assertFalse($payload['isRunning']);
        $this->assertSame(0, $payload['totalItems']);
    }

    public function testTheReadRightRefusalIsReportedAsAPermissionDenial(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->makeEmpty(ImportServiceInterface::class, [
                'checkImportProgress' => static function (): never {
                    throw new ForbiddenException('Access denied to configuration "csv-car-import"');
                },
            ]),
        );

        $this->assertSame(
            'permission_denied',
            $this->decodeToolResult($tool->execute(self::CONFIG_NAME))['code']
        );
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->makeEmpty(ImportServiceInterface::class, [
                'checkImportProgress' => static function (): never {
                    throw new StubFailureException('tmp store unreachable at 10.0.0.7:6379');
                },
            ]),
        );

        $this->assertGenericInternalError($tool->execute(self::CONFIG_NAME), 'get_import_status', '10.0.0.7');
    }

    private function unreachableImportService(): ImportServiceInterface
    {
        return $this->makeEmpty(ImportServiceInterface::class, [
            'checkImportProgress' => static function (): never {
                self::fail('No import progress may be read here.');
            },
        ]);
    }

    private function importServiceReporting(
        bool $isRunning,
        int $total,
        int $processed,
        float $progress
    ): ImportServiceInterface {
        return $this->makeEmpty(ImportServiceInterface::class, [
            'checkImportProgress' => new ImportProgressResponse($isRunning, $total, $processed, $progress),
        ]);
    }

    private function repositoryServing(?Configuration $configuration): ImportConfigurationRepositoryInterface
    {
        return $this->makeEmpty(ImportConfigurationRepositoryInterface::class, [
            'findReadableByName' => $configuration,
        ]);
    }

    private function buildTool(
        bool $allowed,
        ?ImportServiceInterface $importService = null,
        ?ImportConfigurationRepositoryInterface $configurations = null,
    ): GetImportStatusTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new GetImportStatusTool(
            $importService ?? $this->importServiceReporting(false, 0, 0, 0.0),
            $configurations ?? $this->repositoryServing($this->makeEmpty(Configuration::class)),
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
