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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\DirectRowWriter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunNotifier;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolver;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolver;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\MessageBus;

/**
 * The row writer and the merge the bundle wires when Change Control is installed, over these branches.
 */
final readonly class AdHocBranchWayParts
{
    public RowWriterInterface $writer;

    public BranchMerge $merge;

    public function __construct(
        UnitTester $tester,
        EventDispatcherInterface $dispatcher,
        AdHocApplyingBranches $branches
    ) {
        $logger = new NullLogger();
        $objects = new ConcreteObjectResolver();
        $runs = $tester->grabService(RunStoreInterface::class);
        $messages = $tester->grabService(RunMessages::class);

        $this->writer = (new WriteWay(
            new DirectRowWriter($dispatcher, $logger),
            new ObjectChanges(),
            $objects,
            $runs,
            $messages,
            $branches
        ))->writer();
        $this->merge = new BranchMerge(
            $runs,
            $tester->grabService(FileStore::class),
            $tester->grabService(RunNotifier::class),
            $messages,
            new MessageBus(),
            $dispatcher,
            $objects,
            new UserResolver(),
            new LockFactory(new InMemoryStore()),
            $logger,
            $branches
        );
    }
}
