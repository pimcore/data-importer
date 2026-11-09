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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\BranchMerge;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\ObjectChanges;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\WriteWay;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DataImporterRunQueue;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowImporter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunNotifier;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunProcessor;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunService;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolver;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolver;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\MessageBus;

/**
 * The run service and the row processor the bundle wires when Change Control is installed, over these branches.
 */
final readonly class AdHocBranchWayParts
{
    public AdHocImportRunService $service;

    public RunProcessor $processor;

    public function __construct(
        UnitTester $tester,
        EventDispatcherInterface $dispatcher,
        AdHocApplyingBranches $branches
    ) {
        $logger = new NullLogger();
        $objects = new ConcreteObjectResolver();
        $runs = $tester->grabService(RunStoreInterface::class);
        $files = $tester->grabService(FileStore::class);
        $messages = $tester->grabService(RunMessages::class);
        $notifier = $tester->grabService(RunNotifier::class);
        $dryRunMessages = $tester->grabService(DryRunMessages::class);
        $dryRun = $tester->grabService(AdHocImportDryRunServiceInterface::class);

        $writer = (new WriteWay(
            new DirectRowWriter($dispatcher, $logger),
            new ObjectChanges(),
            $objects,
            $runs,
            $messages,
            $branches
        ))->writer();
        $merge = new BranchMerge(
            $runs,
            $files,
            $notifier,
            $messages,
            new MessageBus(),
            $dispatcher,
            $objects,
            new UserResolver(),
            new LockFactory(new InMemoryStore()),
            $logger,
            $branches
        );

        $importer = new RowImporter(
            $writer,
            $tester->grabService(MappingApplicationScope::class),
            $dryRunMessages,
            $messages,
            $dispatcher,
            $logger
        );
        $this->processor = new RunProcessor(
            $runs,
            $dryRun,
            $files,
            $importer,
            $writer,
            $messages,
            $notifier,
            new UserResolver(),
            $logger,
            $merge
        );
        $this->service = new AdHocImportRunService(
            $tester->grabService(SecurityServiceInterface::class),
            $dryRun,
            $runs,
            $files,
            new DataImporterRunQueue($tester->grabService(QueueService::class), $dispatcher),
            $writer,
            $dryRunMessages,
            new LockFactory(new InMemoryStore()),
            $merge
        );
    }
}
