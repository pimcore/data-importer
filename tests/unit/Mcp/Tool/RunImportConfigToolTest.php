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
use Pimcore\Bundle\DataImporterBundle\Exception\QueueNotEmptyException;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\RunImportConfigTool;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportStartResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Tool\ImportConfigurationRepositoryInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotWriteableException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Starting an import writes data objects, so the interesting cases here are the ones that must
 * NOT reach {@see ImportServiceInterface::startImport()}: a caller without the plugin permission,
 * a name that is not a Data Importer configuration, and a name the caller may not read.
 *
 * The per configuration update right is deliberately not re-asserted through the tool. That is
 * the Studio import service's job, and it is the same service the Studio button calls; what is
 * asserted here is that its refusal is reported as a denial rather than as an internal error.
 *
 * @internal
 */
final class RunImportConfigToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CONFIG_NAME = 'csv-car-import';

    private const string NOT_FOUND_MESSAGE = 'Data Importer configuration "csv-car-import" not found.';

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, importService: $this->unreachableImportService());

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. Starting an import must not be what discovers that.
        $tool = new RunImportConfigTool(
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

    public function testANameThatIsNotAReadableImportConfigurationIsNeverStarted(): void
    {
        // The Studio import service resolves a name against every Data Hub configuration, so
        // without this guard a GraphQL or asset configuration could be handed to the importer.
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->unreachableImportService(),
            configurations: $this->repositoryServing(null),
        );

        $this->assertToolError($tool->execute(self::CONFIG_NAME), self::NOT_FOUND_MESSAGE, 'not_found');
    }

    public function testAStartedImportReportsThatRecordsAreQueuedRatherThanFinished(): void
    {
        // The call returns once the queue is filled, so a caller that reads "started" as
        // "imported" would report success before a single object exists.
        $tool = $this->buildTool(allowed: true, importService: $this->importServiceReturning(true));

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME));

        $this->assertTrue($payload['started']);
        $this->assertSame(self::CONFIG_NAME, $payload['name']);
        $this->assertStringContainsString('get_import_status', $payload['next']);
    }

    public function testAnImportThatDoesNotStartIsReportedAsSuccessWithStartedFalse(): void
    {
        // Not an error: the service returning false is an answer about the configuration, and
        // the agent needs to read the flag rather than a thrown exception.
        $tool = $this->buildTool(allowed: true, importService: $this->importServiceReturning(false));

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME));

        $this->assertFalse($payload['started']);
        $this->assertStringContainsString('did not start', $payload['next']);
    }

    public function testAMissingConfigurationBehindTheServiceIsReportedAsNotFound(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->importServiceThrowing(
                new NotFoundHttpException('Configuration with name "csv-car-import" not found')
            ),
        );

        $result = $tool->execute(self::CONFIG_NAME);

        $this->assertSame('not_found', $this->decodeToolResult($result)['code']);
    }

    public function testTheUpdateRightRefusalIsReportedAsAPermissionDenial(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->importServiceThrowing(
                new ForbiddenException('Access denied to configuration "csv-car-import"')
            ),
        );

        $result = $tool->execute(self::CONFIG_NAME);

        $this->assertSame('permission_denied', $this->decodeToolResult($result)['code']);
    }

    public function testAReadOnlyConfigurationStoreIsAPermissionDenialAndNotAnInternalError(): void
    {
        // NotWriteableException is a sibling of ForbiddenException rather than a subclass, so it
        // used to fall through to the generic handler and reach the agent as an opaque failure.
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->importServiceThrowing(
                new NotWriteableException('update', 'Cannot update configuration as it is not writeable.')
            ),
        );

        $result = $tool->execute(self::CONFIG_NAME);

        $this->assertSame('permission_denied', $this->decodeToolResult($result)['code']);
    }

    public function testAnImportAlreadyInFlightIsActionableRatherThanAnInternalError(): void
    {
        // The caller can wait for the queue or cancel it, so the reason has to survive.
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->importServiceThrowing(
                new QueueNotEmptyException('Queue for `csv-car-import` not empty.')
            ),
        );

        $payload = $this->decodeToolResult($tool->execute(self::CONFIG_NAME));

        $this->assertSame('invalid_request', $payload['code']);
        $this->assertStringContainsString('not empty', $payload['error']);
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            importService: $this->importServiceThrowing(
                new StubFailureException('sftp connect failed for user root@sftp.internal')
            ),
        );

        $this->assertGenericInternalError($tool->execute(self::CONFIG_NAME), 'run_import_config', 'sftp.internal');
    }

    /**
     * Fails the test if an import is started at all, which is what the gates are there to prevent.
     */
    private function unreachableImportService(): ImportServiceInterface
    {
        return $this->makeEmpty(ImportServiceInterface::class, [
            'startImport' => static function (): never {
                self::fail('No import may be started here.');
            },
        ]);
    }

    private function importServiceReturning(bool $success): ImportServiceInterface
    {
        return $this->makeEmpty(ImportServiceInterface::class, [
            'startImport' => new ImportStartResponse($success),
        ]);
    }

    private function importServiceThrowing(\Throwable $exception): ImportServiceInterface
    {
        return $this->makeEmpty(ImportServiceInterface::class, [
            'startImport' => static function () use ($exception): never {
                throw $exception;
            },
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
    ): RunImportConfigTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new RunImportConfigTool(
            $importService ?? $this->importServiceReturning(true),
            $configurations ?? $this->repositoryServing($this->makeEmpty(Configuration::class)),
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
