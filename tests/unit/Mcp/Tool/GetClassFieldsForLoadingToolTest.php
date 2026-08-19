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
use Pimcore\Bundle\DataImporterBundle\Tool\LoadableAttributesInterface;
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
        $tool = $this->buildTool(allowed: false, loader: $this->unreachableLoader());

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
            $this->unreachableLoader(),
            $securityService,
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError($tool->execute(self::CLASS_ID), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testReturnsExactlyWhatTheLoaderConsidersLoadable(): void
    {
        // The tool must answer the same question assertAttributeLoadable() asks, so it reads the
        // loader rather than deriving a narrower set from the transformation type matrix.
        $tool = $this->buildTool(allowed: true, loader: $this->loader(['id', 'key', 'name', 'sku']));

        $payload = $this->assertToolSuccess($tool->execute(self::CLASS_ID));

        $this->assertSame(self::CLASS_ID, $payload['classId']);
        $this->assertSame(['id', 'key', 'name', 'sku'], $payload['filterableFields']);
    }

    public function testUnknownClassIsReportedAsNotFoundRatherThanAnEmptySuccess(): void
    {
        // listLoadableAttributes() answers [] for a class id it cannot resolve, which would
        // otherwise be indistinguishable from a class with nothing to load by.
        $tool = $this->buildTool(allowed: true, loader: $this->loader([]));

        $this->assertToolError(
            $tool->execute('does-not-exist'),
            'Class "does-not-exist" not found. Check the class id with the classes section of '
            . 'get_import_config_context.',
            'not_found'
        );
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $tool = $this->buildTool(allowed: true, loader: $this->makeEmpty(
            LoadableAttributesInterface::class,
            [
                'listLoadableAttributes' => static function (): never {
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
     * @param list<string> $attributes
     */
    private function loader(array $attributes): LoadableAttributesInterface
    {
        return $this->makeEmpty(LoadableAttributesInterface::class, ['listLoadableAttributes' => $attributes]);
    }

    private function unreachableLoader(): LoadableAttributesInterface
    {
        return $this->makeEmpty(LoadableAttributesInterface::class, [
            'listLoadableAttributes' => static function (): never {
                self::fail('The loader must not be reached without the permission.');
            },
        ]);
    }

    private function buildTool(
        bool $allowed,
        LoadableAttributesInterface $loader,
    ): GetClassFieldsForLoadingTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new GetClassFieldsForLoadingTool(
            $loader,
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
