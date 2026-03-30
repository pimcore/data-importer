<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle;

use League\FlysystemBundle\FlysystemBundle;
use Pimcore\Bundle\ApplicationLoggerBundle\PimcoreApplicationLoggerBundle;
use Pimcore\Bundle\DataHubBundle\PimcoreDataHubBundle;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\CleanupStrategyConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\InterpreterConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\LoaderConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\MappingConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\CompilerPass\ResolverConfigurationFactoryPass;
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\PimcoreDataImporterExtension;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;
use Pimcore\HttpKernel\Bundle\DependentBundleInterface;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class PimcoreDataImporterBundle extends AbstractPimcoreBundle implements DependentBundleInterface
{
    use PackageVersionTrait;

    public const LOGGER_COMPONENT_PREFIX = 'DATA-IMPORTER ';

    protected function getComposerPackageName(): string
    {
        return 'pimcore/data-importer';
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new PimcoreDataImporterExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        $container
            ->addCompilerPass(new MappingConfigurationFactoryPass())
            ->addCompilerPass(new ResolverConfigurationFactoryPass())
            ->addCompilerPass(new LoaderConfigurationFactoryPass())
            ->addCompilerPass(new InterpreterConfigurationFactoryPass())
            ->addCompilerPass(new CleanupStrategyConfigurationFactoryPass())
        ;
    }

    public static function registerDependentBundles(BundleCollection $collection): void
    {
        $collection->addBundle(PimcoreDataHubBundle::class, 20);
        $collection->addBundle(new FlysystemBundle());
        $collection->addBundle(
            PimcoreApplicationLoggerBundle::class,
            10
        );
    }

    public function getInstaller(): ?InstallerInterface
    {
        return $this->container->get(Installer::class);
    }
}
