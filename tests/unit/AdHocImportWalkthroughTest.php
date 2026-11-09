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

use Codeception\Test\Unit;
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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough\AdHocImportWalkthrough;
use Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough\WalkthroughStep;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ConcreteObjectResolver;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolver;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Contracts\ChangeControl\ConflictPolicy;
use Pimcore\Contracts\ChangeControl\MergeMode;
use Pimcore\Tests\Support\Util\TestHelper;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The walkthrough through both ways an import writes, over real objects and the Data Importer's queue. Change Control
 * is not part of this kernel: the branch way runs on the services the bundle wires for it, over branches that merge
 * for real; the run through Change Control itself is the script's.
 */
class AdHocImportWalkthroughTest extends Unit
{
    private const array ALL_WRITTEN = ['W-1', 'W-2', 'W-3', 'W-4'];

    protected UnitTester $tester;

    private ?AdHocImportWalkthrough $walkthrough = null;

    /** @var list<string> the rows whose PostSaveEvent ran */
    private array $saved = [];

    /** @var list<string> the rows whose PreSaveEvent ran */
    private array $beforeSave = [];

    protected function _after(): void
    {
        $this->tokenStorage()->setToken(null);
        $this->walkthrough?->user()?->delete();
        TestHelper::cleanUp();
    }

    private function tokenStorage(): TokenStorageInterface
    {
        return $this->tester->grabService('security.token_storage');
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->tester->grabService('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }

    private function listenToTheEvents(): void
    {
        $this->dispatcher()->addListener(PreSaveEvent::class, function (PreSaveEvent $event): void {
            $this->beforeSave[] = (string) ($event->getRawData()['sku'] ?? '');
        });
        $this->dispatcher()->addListener(PostSaveEvent::class, function (PostSaveEvent $event): void {
            $this->saved[] = (string) ($event->getRawData()['sku'] ?? '');
        });
    }

    private function walkthrough(
        AdHocImportRunServiceInterface $service,
        \Closure $work,
        ?bool $expectBranch
    ): AdHocImportWalkthrough {
        return $this->walkthrough = new AdHocImportWalkthrough(
            $this->tester->grabService(AdHocImportServiceInterface::class),
            $service,
            $this->tokenStorage(),
            $work,
            AdHocImportWalkthrough::newRun(),
            $expectBranch
        );
    }

    /**
     * @param \Closure(array<string, mixed>): void $process
     */
    private function workOff(\Closure $process): \Closure
    {
        return function (string $fileId) use ($process): void {
            $queue = $this->tester->grabService(QueueService::class);
            $ids = $queue->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
            foreach (array_map(intval(...), $ids) as $id) {
                $item = $queue->getQueueEntryById($id);
                if (($item['configName'] ?? null) === RunRecord::queueName($fileId)) {
                    $process([...$item, 'id' => $id]);
                }
            }
        };
    }

    private function directWay(): AdHocImportWalkthrough
    {
        return $this->walkthrough(
            $this->tester->grabService(AdHocImportRunServiceInterface::class),
            $this->workOff(function (array $item): void {
                $this->tester->grabService(ImportProcessingService::class)->processQueueItem((int) $item['id']);
            }),
            false
        );
    }

    /**
     * The services the bundle wires when Change Control is installed, over these branches.
     *
     * @return array{AdHocImportWalkthrough, AdHocImportRunServiceInterface}
     */
    private function branchWay(AdHocApplyingBranches $branches): array
    {
        $dispatcher = $this->dispatcher();
        $logger = new NullLogger();
        $objects = new ConcreteObjectResolver();
        $users = new UserResolver();
        $runs = $this->tester->grabService(RunStoreInterface::class);
        $files = $this->tester->grabService(FileStore::class);
        $messages = $this->tester->grabService(RunMessages::class);
        $notifier = $this->tester->grabService(RunNotifier::class);
        $dryRunMessages = $this->tester->grabService(DryRunMessages::class);
        $dryRun = $this->tester->grabService(AdHocImportDryRunServiceInterface::class);
        $queue = $this->tester->grabService(QueueService::class);

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
            $users,
            new LockFactory(new InMemoryStore()),
            $logger,
            $branches
        );
        $importer = new RowImporter(
            $writer,
            $this->tester->grabService(MappingApplicationScope::class),
            $dryRunMessages,
            $messages,
            $dispatcher,
            $logger
        );
        $processor = new RunProcessor($runs, $dryRun, $files, $importer, $writer, $messages, $notifier, $users, $logger, $merge);
        $service = new AdHocImportRunService(
            $this->tester->grabService(SecurityServiceInterface::class),
            $dryRun,
            $runs,
            $files,
            new DataImporterRunQueue($queue, $dispatcher),
            $writer,
            $dryRunMessages,
            new LockFactory(new InMemoryStore()),
            $merge
        );

        $walkthrough = $this->walkthrough(
            $service,
            $this->workOff(static function (array $item) use ($processor, $queue): void {
                $processor->processRow((string) $item['configName'], (int) $item['userOwner'], (string) $item['data']);
                $queue->markQueueEntryAsProcessed((int) $item['id']);
            }),
            true
        );

        return [$walkthrough, $service];
    }

    /**
     * @param list<WalkthroughStep> $steps
     */
    private function assertEveryStepPasses(array $steps, int $count): void
    {
        $report = implode("\n", array_map(static fn (WalkthroughStep $step): string => $step->render(), $steps));
        $this->assertCount($count, $steps, $report);
        foreach ($steps as $step) {
            $this->assertTrue($step->passed(), $report);
        }
    }

    public function testWrittenDirectlyWithTheEventsOfAnImport(): void
    {
        $this->listenToTheEvents();

        $this->assertEveryStepPasses($this->directWay()->run(), 3);

        $this->assertSame(self::ALL_WRITTEN, $this->saved);
        $this->assertSame(self::ALL_WRITTEN, $this->beforeSave, 'the refused row gets no event');
    }

    public function testThroughABranchThatIsMerged(): void
    {
        $this->listenToTheEvents();
        $branches = new AdHocApplyingBranches();
        [$walkthrough] = $this->branchWay($branches);

        $this->assertEveryStepPasses($walkthrough->run(), 4);

        $this->assertSame(self::ALL_WRITTEN, $this->saved, 'PostSaveEvent only for what the merge wrote');
        $this->assertSame([...self::ALL_WRITTEN, 'W-5'], $this->beforeSave);
        $this->assertSame(1, $branches->discards, 'the discarded import closes its branch');
        foreach ($branches->merges as [$mode, $conflicts, $userName]) {
            $this->assertSame(MergeMode::Save, $mode);
            $this->assertSame(ConflictPolicy::BranchWins, $conflicts);
            $this->assertSame($walkthrough->user()?->getName(), $userName);
        }
        $this->assertCount(3, $branches->merges, 'one merge per import that was not discarded');
    }

    public function testAMergeThatCannotWriteARowEndsAsAPartialResult(): void
    {
        $this->listenToTheEvents();
        [$walkthrough, $service] = $this->branchWay(new AdHocApplyingBranches(failing: ['W-1']));

        [$fileId] = $walkthrough->import(['W-1;Chair;Stuhl;1', 'W-2;Lamp;Lampe;2']);

        $status = $service->status($fileId);
        $this->assertSame('done', $status->getState());
        $this->assertSame([1, 1], [$status->getCreated(), $status->getFailed()]);
        $this->assertNull($walkthrough->read('W-1'), 'the row the merge could not write');
        $this->assertSame('Lamp', $walkthrough->read('W-2')?->get('name'), 'what was written stays');
        $this->assertSame(['W-1'], array_column($status->getFailures(), 'matchValue'));
        $this->assertStringNotContainsString(
            'the reason of Change Control',
            (string) $status->getFailures()[0]['message'],
            'told in the words of the import'
        );
        $this->assertSame(['W-2'], $this->saved, 'PostSaveEvent only for what the merge wrote');

        $service->discard($fileId);
    }

    public function testAMergeRefusedByItsCheckWaitsForTheChoiceAndMergesWithoutTheRow(): void
    {
        $branches = new AdHocApplyingBranches(refusing: ['W-1']);
        [$walkthrough, $service] = $this->branchWay($branches);

        [$fileId] = $walkthrough->import(['W-1;Chair;Stuhl;1', 'W-2;Lamp;Lampe;2']);

        $status = $service->status($fileId);
        $this->assertSame('decision', $status->getState());
        $this->assertSame(['W-1'], array_column($status->getFailures(), 'matchValue'));
        $this->assertNull($walkthrough->read('W-2'), 'nothing is merged yet');

        $branches->refusing = [];
        $merged = $service->mergeLeavingOut($fileId);

        $this->assertSame('done', $merged->getState());
        $this->assertSame([1, 1], [$merged->getCreated(), $merged->getFailed()]);
        $this->assertSame(['row-2'], $branches->takenOut, 'the row of W-1');
        $this->assertNull($walkthrough->read('W-1'));
        $this->assertSame('Lamp', $walkthrough->read('W-2')?->get('name'));

        $service->discard($fileId);
    }
}
