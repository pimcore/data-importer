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
use Pimcore\Bundle\DataImporterBundle\Preview\NoPreviewScopeResolver;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScopeResolverInterface;
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataImporterSnapshotCollector;
use Pimcore\Bundle\DataImporterBundle\Telemetry\DataImporterUsageProvider;
use Pimcore\Bundle\DataImporterBundle\Telemetry\ImportStartTelemetrySubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

        $this->assertSame(
            ConfigProposalLane::canReview($container) ? ChangeSetPreviewScopeResolver::class : NoPreviewScopeResolver::class,
            $container->getDefinition(PreviewScopeResolverInterface::class)->getClass()
        );
    }
}
