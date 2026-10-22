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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\DependencyInjection;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataHubBundle\DependencyInjection\ConfigProposalLane;
use Pimcore\Bundle\DataImporterBundle\ChangeControl\ChangeSetPreviewScopeResolver;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\PimcoreDataImporterExtension;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\ProposeImportConfigTool;
use Pimcore\Bundle\DataImporterBundle\Preview\NoPreviewScopeResolver;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScopeResolverInterface;
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataImporterSnapshotCollector;
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataImporterUsageProvider;
use Pimcore\Bundle\DataImporterBundle\Telemetry\ImportStartTelemetrySubscriber;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class PimcoreDataImporterExtensionTest extends Unit
{
    public function testLoadRegistersTheTelemetryServices(): void
    {
        $container = new ContainerBuilder();

        (new PimcoreDataImporterExtension())->load([], $container);

        $this->assertTrue($container->hasDefinition(DataImporterUsageProvider::class));
        $this->assertTrue($container->hasDefinition(DataImporterSnapshotCollector::class));
        $this->assertTrue($container->hasDefinition(ImportStartTelemetrySubscriber::class));
    }

    /** a scope binds to a change set only where Change Control can tell which ones the user may read */
    public function testScopedPreviewsAreBoundByChangeControlOnlyWhereItIsInstalled(): void
    {
        $container = new ContainerBuilder();

        (new PimcoreDataImporterExtension())->load([], $container);

        $expected = ConfigProposalLane::canReview($container)
            ? ChangeSetPreviewScopeResolver::class
            : NoPreviewScopeResolver::class;
        $this->assertSame(
            $expected,
            $container->getDefinition(PreviewScopeResolverInterface::class)->getClass()
        );
    }

    public function testTheProposeToolIsRegisteredOnlyWhereAProposalCanBeMade(): void
    {
        $container = new ContainerBuilder();

        (new PimcoreDataImporterExtension())->load([], $container);

        $this->assertSame(
            ConfigProposalLane::canPropose($container),
            $container->hasDefinition(ProposeImportConfigTool::class)
        );
    }

    public function testWithoutTheAgentBundleNothingIsContributedToIt(): void
    {
        $container = $this->prepended(null);

        $this->assertSame([], $container->getExtensionConfig('pimcore_agent'));
    }

    public function testTheSkillAndToolsAreContributedToTheConfigurationAgent(): void
    {
        $container = $this->prepended(true);
        if (!ConfigProposalLane::canPropose($container)) {
            static::markTestSkipped('Change Control, the MCP host or the Agent bundle not installed');
        }

        [$config] = $container->getExtensionConfig('pimcore_agent');
        $this->assertStringEndsWith('/Resources/skills', $config['skills']['paths'][0]);
        $this->assertSame(
            ['pimcore-data-importer-read', 'pimcore-data-importer-propose'],
            $config['agents']['contributions']['configuration']['pimcoreMcpServers']
        );
        $this->assertSame(
            ['data-importer-configuration'],
            $config['agents']['contributions']['configuration']['skills']
        );
    }

    /** an agent bundle released before the contributions node would reject the key */
    public function testAnAgentBundleWithoutContributionsGetsTheSkillPathOnly(): void
    {
        [$config] = $this->prepended(false)->getExtensionConfig('pimcore_agent');

        $this->assertArrayHasKey('skills', $config);
        $this->assertArrayNotHasKey('agents', $config);
    }

    public function testAnAgentBundleWithoutAConfigurationTreeGetsTheSkillPathOnly(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/app');
        $container->registerExtension($this->extension('pimcore_studio_backend', null));
        // an extension that offers no configuration tree at all
        $container->registerExtension(new class() implements ExtensionInterface {
            public function getAlias(): string
            {
                return 'pimcore_agent';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
                // only its missing configuration tree matters to prepend()
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): string|false
            {
                return false;
            }
        });
        if (!ConfigProposalLane::canPropose($container)) {
            static::markTestSkipped('Change Control, the MCP host or the Agent bundle not installed');
        }

        (new PimcoreDataImporterExtension())->prepend($container);

        [$config] = $container->getExtensionConfig('pimcore_agent');
        $this->assertArrayHasKey('skills', $config);
        $this->assertArrayNotHasKey('agents', $config);
    }

    /** @param bool|null $contributions null: no agent bundle; else whether it takes contributions */
    private function prepended(?bool $contributions): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/app');
        $container->registerExtension($this->extension('pimcore_studio_backend', null));
        if ($contributions !== null) {
            $container->registerExtension($this->extension('pimcore_agent', $contributions));
        }

        (new PimcoreDataImporterExtension())->prepend($container);

        return $container;
    }

    private function extension(string $alias, ?bool $contributions): Extension
    {
        return new class($alias, $contributions) extends Extension {
            public function __construct(private readonly string $alias, private readonly ?bool $contributions)
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
                // only its configuration tree matters to prepend()
            }

            public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
            {
                return new class($this->alias, $this->contributions === true) implements ConfigurationInterface {
                    public function __construct(private readonly string $alias, private readonly bool $contributions)
                    {
                    }

                    public function getConfigTreeBuilder(): TreeBuilder
                    {
                        $tree = new TreeBuilder($this->alias);
                        $agents = $tree->getRootNode()->children()->arrayNode('agents')->children();
                        if ($this->contributions) {
                            $agents->variableNode('contributions');
                        }

                        return $tree;
                    }
                };
            }
        };
    }
}
