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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\GetClassFieldsForLoadingTool;
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
final class GetClassFieldsForLoadingToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CLASS_ID = 'CAR';

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, schemaService: $this->unreachableSchemaService());

        $this->assertToolError($tool->execute(self::CLASS_ID), self::MISSING_PERMISSION, 'permission_denied');
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

        $tool = new GetClassFieldsForLoadingTool(
            $this->unreachableSchemaService(),
            $securityService,
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError($tool->execute(self::CLASS_ID), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testReturnsTheUnionOfTheLoadableTypesSortedAndDeduplicated(): void
    {
        $tool = $this->buildTool(allowed: true, schemaService: $this->schemaService([
            'default' => ['name', 'sku'],
            'numeric' => ['price', 'sku'],
            'calculated' => ['margin'],
            // Not loadable: must not appear even though the matrix carries them.
            'date' => ['releaseDate'],
            'dataObject' => ['manufacturer'],
        ]));

        $payload = $this->assertToolSuccess($tool->execute(self::CLASS_ID));

        $this->assertSame(self::CLASS_ID, $payload['classId']);
        $this->assertSame(['margin', 'name', 'price', 'sku'], $payload['filterableFields']);
    }

    public function testAMatrixWithoutTheLoadableTypesIsReportedAsNotFound(): void
    {
        // A class that exists but exposes nothing filterable answers exactly like an unknown one,
        // which is the point: both are a dead end the agent must fix by picking another class id.
        $tool = $this->buildTool(allowed: true, schemaService: $this->schemaService([
            'date' => ['releaseDate'],
        ]));

        $this->assertToolError(
            $tool->execute(self::CLASS_ID),
            'No loadable fields for class "CAR". Check the class id with the classes section '
            . 'of get_import_config_context.',
            'not_found'
        );
    }

    public function testUnknownClassIsReportedAsNotFoundRatherThanAnEmptySuccess(): void
    {
        // getFieldTypeMatrix() answers [] for a class id it cannot resolve.
        $tool = $this->buildTool(allowed: true, schemaService: $this->schemaService([]));

        $this->assertToolError(
            $tool->execute('does-not-exist'),
            'No loadable fields for class "does-not-exist". Check the class id with the classes '
            . 'section of get_import_config_context.',
            'not_found'
        );
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $tool = $this->buildTool(allowed: true, schemaService: $this->makeEmpty(
            ConfigurationSchemaService::class,
            [
                'getFieldTypeMatrix' => static function (): never {
                    throw new StubFailureException('class definition storage unreachable at 10.0.0.5:3306');
                },
            ]
        ));

        $this->assertGenericInternalError(
            $tool->execute(self::CLASS_ID),
            'get_class_fields_for_loading',
            '10.0.0.5'
        );
    }

    /**
     * @param array<string, list<string>> $matrix
     */
    private function schemaService(array $matrix): ConfigurationSchemaService
    {
        return $this->makeEmpty(ConfigurationSchemaService::class, ['getFieldTypeMatrix' => $matrix]);
    }

    private function unreachableSchemaService(): ConfigurationSchemaService
    {
        return $this->makeEmpty(ConfigurationSchemaService::class, [
            'getFieldTypeMatrix' => static function (): never {
                self::fail('The schema service must not be reached without the permission.');
            },
        ]);
    }

    private function buildTool(
        bool $allowed,
        ConfigurationSchemaService $schemaService,
    ): GetClassFieldsForLoadingTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new GetClassFieldsForLoadingTool(
            $schemaService,
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
