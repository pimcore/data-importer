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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ListImportConfigsTool;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits\McpToolResultTrait;
use Pimcore\Bundle\DataImporterBundle\Tool\ImportConfigurationRepositoryInterface;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\ConfigurationTypes;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\UserNotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Mcp\Tool\McpToolErrorHandler;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\UserInterface;
use Psr\Log\NullLogger;

/**
 * The tool asks {@see ImportConfigurationRepositoryInterface} for the configurations the current
 * user may read, so every test here stubs that one seam: no container, no settings store, no
 * static Data Hub model and nothing global to restore afterwards.
 *
 * Which configurations count as readable, the adapter type filter and the per configuration read
 * right, is the repository's job and is deliberately not re-asserted through the tool.
 *
 * @internal
 */
final class ListImportConfigsToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CONFIG_NAME = 'csv-car-import';

    private const string SECOND_CONFIG_NAME = 'json-part-import';

    private const int MODIFICATION_DATE = 1769026708;

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, configurations: $this->unreachableRepository());

        $this->assertToolError($tool->execute(), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure.
        $tool = new ListImportConfigsTool(
            $this->unreachableRepository(),
            $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError($tool->execute(), self::MISSING_PERMISSION, 'permission_denied');
    }

    public function testEachConfigurationIsDescribedByWhatMattersBeforeReadingIt(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            configurations: $this->repositoryServing(
                $this->importerConfiguration(self::CONFIG_NAME, group: 'catalog', active: true, classId: '6')
            ),
        );

        $payload = $this->assertToolSuccess($tool->execute());

        $this->assertSame(['configurations'], array_keys($payload));
        $this->assertSame(
            [[
                'name' => self::CONFIG_NAME,
                'group' => 'catalog',
                'active' => true,
                'targetClassId' => '6',
                'modificationDate' => self::MODIFICATION_DATE,
            ]],
            $payload['configurations'],
        );
    }

    public function testEveryConfigurationTheRepositoryServesIsListedInOrder(): void
    {
        // The tool adds no filter and no sort of its own: what the repository calls readable is
        // the whole answer, in the order it hands it over.
        $tool = $this->buildTool(
            allowed: true,
            configurations: $this->repositoryServing(
                $this->importerConfiguration(self::SECOND_CONFIG_NAME),
                $this->importerConfiguration(self::CONFIG_NAME),
            ),
        );

        $payload = $this->assertToolSuccess($tool->execute());

        $this->assertSame(
            [self::SECOND_CONFIG_NAME, self::CONFIG_NAME],
            array_column($payload['configurations'], 'name'),
        );
    }

    public function testAConfigurationWithoutAnActiveFlagIsReportedAsInactive(): void
    {
        // An import that never runs is the single most common surprise here, so the flag has to be
        // false rather than missing when the stored configuration does not carry it.
        $tool = $this->buildTool(
            allowed: true,
            configurations: $this->repositoryServing(
                $this->importerConfiguration(self::CONFIG_NAME, active: null, classId: null)
            ),
        );

        $payload = $this->assertToolSuccess($tool->execute());

        $this->assertFalse($payload['configurations'][0]['active']);
        $this->assertNull($payload['configurations'][0]['targetClassId']);
    }

    public function testNothingReadableIsAnEmptyListRatherThanAnError(): void
    {
        $payload = $this->assertToolSuccess($this->buildTool(allowed: true)->execute());

        $this->assertSame(['configurations' => []], $payload);
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            configurations: $this->makeEmpty(ImportConfigurationRepositoryInterface::class, [
                'findReadable' => static function (): never {
                    throw new StubFailureException('config storage unreachable at 10.0.0.5:3306');
                },
            ]),
        );

        $this->assertGenericInternalError($tool->execute(), 'list_import_configs', '10.0.0.5');
    }

    /**
     * Fails the test if any configuration is read at all, which is what the permission gate is
     * there to prevent.
     */
    private function unreachableRepository(): ImportConfigurationRepositoryInterface
    {
        return $this->makeEmpty(ImportConfigurationRepositoryInterface::class, [
            'findReadable' => static function (): never {
                self::fail('No Data Importer configuration may be read here.');
            },
            'findReadableByName' => static function (): never {
                self::fail('No Data Importer configuration may be read here.');
            },
        ]);
    }

    private function repositoryServing(Configuration ...$configurations): ImportConfigurationRepositoryInterface
    {
        return $this->makeEmpty(ImportConfigurationRepositoryInterface::class, [
            'findReadable' => $configurations,
        ]);
    }

    /**
     * A configuration as the repository hands it over. Only what the tool actually reads is
     * populated, so a read the tool stops doing shows up as a missing value rather than being
     * covered by a fixture that carries it anyway.
     *
     * A null $active omits the flag from the stored payload, a null $classId omits the whole
     * resolver block, which is how a half configured import is stored.
     */
    private function importerConfiguration(
        string $name,
        string $group = '',
        ?bool $active = false,
        ?string $classId = '6',
    ): Configuration {
        $general = [
            'name' => $name,
            'type' => ConfigurationTypes::DATA_IMPORTER_DATA_OBJECT,
            'path' => '',
            'group' => $group,
            'modificationDate' => self::MODIFICATION_DATE,
            'createDate' => 1769026497,
        ];
        if ($active !== null) {
            $general['active'] = $active;
        }

        $stored = ['general' => $general];
        if ($classId !== null) {
            $stored['resolverConfig'] = ['dataObjectClassId' => $classId];
        }

        return $this->makeEmpty(Configuration::class, [
            'getName' => $name,
            'getGroup' => $group,
            'getModificationDate' => self::MODIFICATION_DATE,
            'getConfiguration' => $stored,
        ]);
    }

    private function buildTool(
        bool $allowed,
        ?ImportConfigurationRepositoryInterface $configurations = null,
    ): ListImportConfigsTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new ListImportConfigsTool(
            $configurations ?? $this->repositoryServing(),
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
