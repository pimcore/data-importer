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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Helper\Traits;

use Doctrine\DBAL\Connection;
use Pimcore;
use Pimcore\Bundle\DataHubBundle\Configuration\Dao;
use Pimcore\Bundle\DataImporterBundle\Tests\unit\Mcp\Tool\StubFailureException;
use Pimcore\Model\Dao\PimcoreLocationAwareConfigDao;
use Pimcore\Model\User;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * In-memory Data Hub configurations for the MCP tools that read through the static
 * `Configuration` model instead of an injected service.
 *
 * That model resolves its storage through `Pimcore::getContainer()`, so the only seam available
 * to a unit test is the container itself. These helpers stand a stub kernel in front of it whose
 * `pimcore_data_hub` parameter declares `symfony-config` as the read target, which makes
 * `Configuration::getByName()` answer from the array handed in here and never reach the settings
 * store, the database or the filesystem.
 *
 * Everything installed is global, so {@see self::restoreDataHubConfigurations()} belongs in the
 * `_after` of every test class that uses this, and it also clears the two private static caches
 * the Data Hub DAOs keep, which would otherwise carry one test's fixture into the next.
 */
trait DataHubConfigurationFixtureTrait
{
    private bool $dataHubKernelInstalled = false;

    private ?KernelInterface $originalDataHubKernel = null;

    /**
     * @param array<string, array<string, mixed>> $configurations keyed by configuration name
     */
    private function giveDataHubConfigurations(array $configurations, ?User $currentUser = null): void
    {
        $userResolver = new class ($currentUser) {
            public function __construct(private readonly ?User $user)
            {
            }

            public function getUser(): ?User
            {
                return $this->user;
            }
        };

        // Listing all configurations also asks the settings store for its ids, which goes through
        // the DBAL connection in the container. Answering with no rows keeps the fixture the only
        // source of configurations, so the test cannot see whatever the local database holds.
        $connection = $this->makeEmpty(Connection::class, ['fetchFirstColumn' => []]);

        $this->installDataHubContainer(
            $this->makeEmpty(ContainerInterface::class, [
                'getParameter' => [
                    'configurations' => $configurations,
                    'config_location' => [
                        'data_hub' => [
                            'read_target' => ['type' => 'symfony-config'],
                            'write_target' => ['type' => 'symfony-config'],
                        ],
                    ],
                ],
                'get' => static fn (string $id): object => $id === 'doctrine.dbal.default_connection'
                    ? $connection
                    : $userResolver,
            ])
        );
    }

    /**
     * Fails the test if the Data Hub configuration storage is consulted at all.
     */
    private function expectNoDataHubConfigurationAccess(): void
    {
        $this->installDataHubContainer(
            $this->makeEmpty(ContainerInterface::class, [
                'getParameter' => static function (): never {
                    self::fail('No Data Hub configuration may be read here.');
                },
            ])
        );
    }

    /**
     * Makes the Data Hub configuration storage fail the way an unreachable backend would.
     */
    private function breakDataHubConfigurationAccess(string $message): void
    {
        $this->installDataHubContainer(
            $this->makeEmpty(ContainerInterface::class, [
                'getParameter' => static function () use ($message): never {
                    throw new StubFailureException($message);
                },
            ])
        );
    }

    private function restoreDataHubConfigurations(): void
    {
        if (!$this->dataHubKernelInstalled) {
            return;
        }

        $this->writeStatic(Pimcore::class, 'kernel', $this->originalDataHubKernel);
        $this->clearDataHubConfigurationCaches();
        $this->dataHubKernelInstalled = false;
        $this->originalDataHubKernel = null;
    }

    private function installDataHubContainer(ContainerInterface $container): void
    {
        if (!$this->dataHubKernelInstalled) {
            $this->originalDataHubKernel = Pimcore::hasKernel() ? Pimcore::getKernel() : null;
            $this->dataHubKernelInstalled = true;
        }

        $this->writeStatic(
            Pimcore::class,
            'kernel',
            $this->makeEmpty(KernelInterface::class, ['getContainer' => $container])
        );
        $this->clearDataHubConfigurationCaches();
    }

    private function clearDataHubConfigurationCaches(): void
    {
        $this->writeStatic(PimcoreLocationAwareConfigDao::class, 'cache', []);
        $this->writeStatic(Dao::class, '_config', null);
    }

    private function writeStatic(string $class, string $property, mixed $value): void
    {
        (new ReflectionProperty($class, $property))->setValue(null, $value);
    }
}
