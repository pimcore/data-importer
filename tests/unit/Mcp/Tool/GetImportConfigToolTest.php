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
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\GetImportConfigTool;
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
 * The tool asks {@see ImportConfigurationRepositoryInterface} for one configuration by name, so
 * every test here stubs that one seam: no container, no settings store, no static Data Hub model
 * and nothing global to restore afterwards.
 *
 * Deciding which names are readable, the adapter type filter and the per configuration read right,
 * is the repository's job. All the tool sees is a configuration or a null, which is why the three
 * reasons a name can be withheld collapse into one answer here.
 *
 * @internal
 */
final class GetImportConfigToolTest extends Unit
{
    use McpToolResultTrait;

    private const string MISSING_PERMISSION = 'Missing permission '
        . PermissionConstants::PLUGIN_DATA_IMPORTER_CONFIG;

    private const string CONFIG_NAME = 'csv-car-import';

    private const int MODIFICATION_DATE = 1769026708;

    public function testDeniedWhenMissingPermission(): void
    {
        $tool = $this->buildTool(allowed: false, configurations: $this->unreachableRepository());

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testUnauthenticatedCallerIsDeniedRatherThanErroring(): void
    {
        // The MCP firewall is stateless, so an expired or revoked bearer reaches the tool with
        // no resolvable user. That must read as a denial, not as an internal failure.
        $tool = new GetImportConfigTool(
            $this->unreachableRepository(),
            $this->makeEmpty(SecurityServiceInterface::class, [
                'getCurrentUser' => static function (): never {
                    throw new UserNotFoundException();
                },
            ]),
            new McpToolErrorHandler(new NullLogger()),
        );

        $this->assertToolError(
            $tool->execute(self::CONFIG_NAME),
            self::MISSING_PERMISSION,
            'permission_denied'
        );
    }

    public function testTheStoredConfigurationIsReturnedWholeUnderTheRequestedName(): void
    {
        $stored = $this->storedConfiguration(self::CONFIG_NAME);
        $tool = $this->buildTool(
            allowed: true,
            configurations: $this->repositoryServing($this->importerConfiguration($stored), $requested),
        );

        $payload = $this->assertToolSuccess($tool->execute(self::CONFIG_NAME));

        $this->assertSame(self::CONFIG_NAME, $requested, 'The tool must look up the name it was given.');
        $this->assertSame(['name', 'modificationDate', 'configuration'], array_keys($payload));
        $this->assertSame(self::CONFIG_NAME, $payload['name']);
        // The modification date is what save_import_config needs for optimistic locking.
        $this->assertSame(self::MODIFICATION_DATE, $payload['modificationDate']);
        // Whole and verbatim, down to the general block Data Hub stamps with whether this
        // instance may write it back: what comes back has to be what save_import_config takes.
        $this->assertSame($stored, $payload['configuration']);
    }

    /**
     * Unknown, owned by another Data Hub adapter, or not readable by this user: the repository
     * answers null to all three and every one of them has to read as not_found. Answering
     * permission_denied to the last one would confirm to the agent, and to whoever is driving it,
     * that a configuration it may not read exists.
     *
     * @dataProvider withheldNameProvider
     */
    public function testANameTheRepositoryWithholdsIsReportedAsNotFound(string $name): void
    {
        $tool = $this->buildTool(allowed: true, configurations: $this->repositoryServing(null, $requested));

        $this->assertToolError(
            $tool->execute($name),
            'Data Importer configuration "' . $name . '" not found.',
            'not_found'
        );
        $this->assertSame($name, $requested, 'The tool must look up the name it was given.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function withheldNameProvider(): array
    {
        return [
            'no configuration of that name' => ['typo-import'],
            'a configuration of another adapter' => ['graphql-cars'],
            'a configuration this user may not read' => [self::CONFIG_NAME],
        ];
    }

    public function testGenericFailureIsGenericisedAndNeverLeaksTheRawMessage(): void
    {
        $tool = $this->buildTool(
            allowed: true,
            configurations: $this->makeEmpty(ImportConfigurationRepositoryInterface::class, [
                'findReadableByName' => static function (): never {
                    throw new StubFailureException('config storage unreachable at db.internal:3306');
                },
            ]),
        );

        $this->assertGenericInternalError(
            $tool->execute(self::CONFIG_NAME),
            'get_import_config',
            'db.internal'
        );
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

    /**
     * Answers one lookup with $configuration, or withholds the name by answering null, and
     * records the name it was asked for in $requested.
     *
     * The name is recorded rather than asserted inside the stub because the tool catches every
     * Throwable the repository raises, which would swallow a failed assertion and report it as an
     * internal error instead of as the mismatch it is.
     */
    private function repositoryServing(
        ?Configuration $configuration,
        ?string &$requested,
    ): ImportConfigurationRepositoryInterface {
        $requested = null;

        return $this->makeEmpty(ImportConfigurationRepositoryInterface::class, [
            'findReadableByName' => static function (string $name) use ($configuration, &$requested): ?Configuration {
                $requested = $name;

                return $configuration;
            },
        ]);
    }

    /**
     * A configuration as the repository hands it over. Only the two reads the tool makes are
     * populated, so a read it stops making shows up as a missing value rather than being covered
     * by a fixture that carries it anyway.
     *
     * @param array<string, mixed> $stored
     */
    private function importerConfiguration(array $stored): Configuration
    {
        return $this->makeEmpty(Configuration::class, [
            'getModificationDate' => self::MODIFICATION_DATE,
            'getConfiguration' => $stored,
        ]);
    }

    /**
     * What Data Hub returns for a stored import configuration, trimmed to one loader and one
     * mapping row: enough shape to tell a whole answer from a partial one.
     *
     * @return array<string, mixed>
     */
    private function storedConfiguration(string $name): array
    {
        return [
            'general' => [
                'name' => $name,
                'type' => ConfigurationTypes::DATA_IMPORTER_DATA_OBJECT,
                'path' => '',
                'group' => '',
                'active' => true,
                'modificationDate' => self::MODIFICATION_DATE,
                'createDate' => 1769026497,
                'writeable' => false,
            ],
            'loaderConfig' => ['type' => 'push'],
            'mappingConfig' => [['label' => 'Name', 'dataSourceIndex' => 0]],
        ];
    }

    private function buildTool(
        bool $allowed,
        ?ImportConfigurationRepositoryInterface $configurations = null,
    ): GetImportConfigTool {
        $user = $this->makeEmpty(UserInterface::class, ['isAllowed' => $allowed]);

        return new GetImportConfigTool(
            $configurations ?? $this->makeEmpty(ImportConfigurationRepositoryInterface::class),
            $this->makeEmpty(SecurityServiceInterface::class, ['getCurrentUser' => $user]),
            new McpToolErrorHandler(new NullLogger()),
        );
    }
}
