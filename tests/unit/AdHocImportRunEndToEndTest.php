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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\RowMatcherModel;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Notification\Listing as NotificationListing;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace\DataObject as ObjectWorkspace;
use Pimcore\Security\User\User as SecurityUser;
use Pimcore\Tests\Support\Util\TestHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * One path through a run over real objects, from the queue to the result. New objects only: saving an object again
 * reaches the search index, which the test environment does not have; updates are the unit tests'.
 */
class AdHocImportRunEndToEndTest extends Unit
{
    private const string TARGET = '/di-ad-hoc-run/target';

    private const string FIRST_OBJECT = self::TARGET . '/RUN-1';

    private const string SECOND_OBJECT = self::TARGET . '/RUN-2';

    private const string NOTHING_WRITTEN = 'nothing is written';

    private const string CSV = "sku;name\nRUN-1;Chair\nRUN-1;Again\nRUN-2;Lamp\n";

    protected UnitTester $tester;

    private ClassDefinition $class;

    private int $folderId;

    private User $user;

    private string $path;

    protected function _before(): void
    {
        $this->class = ClassDefinition::getByName(RowMatcherModel::CLASS_NAME) ?? RowMatcherModel::createClass();
        $this->folderId = (int) Service::createFolderByPath(self::TARGET)->getId();

        $this->user = new User();
        $this->user->setParentId(0);
        $this->user->setName(uniqid('ad-hoc-run-', true));
        $this->user->setActive(true);
        $this->user->setAdmin(true);
        $this->user->save();

        $this->logInAs($this->user);

        $this->path = sys_get_temp_dir() . '/' . uniqid('ad-hoc-run-', true);
        file_put_contents($this->path, self::CSV);
    }

    protected function _after(): void
    {
        @unlink($this->path);
        $this->tokenStorage()->setToken(null);
        $this->user->delete();
        TestHelper::cleanUp();
    }

    private function logInAs(User $user): void
    {
        $securityUser = new SecurityUser($user);
        $this->tokenStorage()->setToken(
            new UsernamePasswordToken($securityUser, 'pimcore_studio', $securityUser->getRoles())
        );
    }

    private function tokenStorage(): TokenStorageInterface
    {
        return $this->tester->grabService('security.token_storage');
    }

    /**
     * @param list<array<string, string>> $columns
     */
    private function parameters(
        array $columns = [['column' => 'sku', 'field' => 'sku'], ['column' => 'name', 'field' => 'name']]
    ): AdHocImportRunParameters {
        return new AdHocImportRunParameters(
            new AdHocImportDryRunParameters(
                $this->folderId,
                $this->class->getId(),
                AdHocImportDryRunParameters::MATCH_BY_FIELD,
                'sku',
                'sku',
                columns: $columns
            ),
            AdHocImportDryRunServiceInterface::SKIP_ROWS,
            'products.csv'
        );
    }

    /**
     * @return list<int>
     */
    private function queuedIds(): array
    {
        $queue = $this->tester->grabService(QueueService::class);

        return array_map(intval(...), $queue->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL));
    }

    private function processQueued(int $id): void
    {
        $this->tester->grabService(ImportProcessingService::class)->processQueueItem($id);
    }

    private function drainQueue(): void
    {
        foreach ($this->queuedIds() as $id) {
            $this->processQueued($id);
        }
    }

    private function uploadedFile(): string
    {
        $fileId = $this->tester->grabService(AdHocImportServiceInterface::class)
            ->upload(new UploadedFile($this->path, 'products.csv', null, null, true), $this->folderId)
            ->getFileId();
        $this->assertNotNull($fileId);

        return $fileId;
    }

    /**
     * @param array<string, bool|string> $rights
     */
    private function restrictUserToTheTarget(array $rights): void
    {
        $workspace = new ObjectWorkspace();
        $workspace->setCid($this->folderId);
        $workspace->setCpath(self::TARGET);
        $workspace->setValues($rights);
        $this->user->setAdmin(false);
        $this->user->setPermission('objects', true);
        $this->user->setWorkspacesObject([$workspace]);
        $this->user->save();
        $this->logInAs($this->user);
    }

    public function testAFileIsImportedThroughTheQueueWithItsEventsResultAndNotification(): void
    {
        $fileId = $this->uploadedFile();
        $service = $this->tester->grabService(AdHocImportRunServiceInterface::class);

        $dispatcher = $this->tester->grabService('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);
        $seen = [];
        $dispatcher->addListener(PreSaveEvent::class, static function (PreSaveEvent $event) use (&$seen): void {
            $seen[] = ['pre', $event->getConfigName(), $event->getRawData()['sku'] ?? null];
        });
        $dispatcher->addListener(PostSaveEvent::class, static function (PostSaveEvent $event) use (&$seen): void {
            $seen[] = ['post', $event->getConfigName(), $event->getDataObject()->getId() !== null];
        });

        $started = $service->start($fileId, $this->parameters());

        $this->assertSame('running', $started->getState());
        $this->assertSame(2, $started->getTotal());
        $this->assertSame(1, $started->getSkipped(), 'the second RUN-1 is an error row');

        $this->drainQueue();

        $run = $service->status($fileId);
        $this->assertSame('done', $run->getState());
        $this->assertSame(2, $run->getCreated());
        $this->assertSame(0, $run->getFailed());

        $object = DataObject::getByPath(self::FIRST_OBJECT);
        $this->assertInstanceOf(Concrete::class, $object);
        $this->assertSame('Chair', $object->get('name'));
        $this->assertFalse($object->isPublished(), 'new objects stay unpublished');
        $this->assertSame($this->user->getId(), $object->getUserModification());
        $this->assertSame($this->user->getId(), $object->getUserOwner());
        $this->assertInstanceOf(Concrete::class, DataObject::getByPath(self::SECOND_OBJECT));

        $this->assertSame([
            ['pre', '', 'RUN-1'],
            ['post', '', true],
            ['pre', '', 'RUN-2'],
            ['post', '', true],
        ], $seen);

        $store = $this->tester->grabService(FileStore::class);
        $this->assertNull($store->findType($this->user->getId(), $fileId), 'the upload is gone');

        $notifications = new NotificationListing();
        $notifications->setCondition('recipient = ?', [$this->user->getId()]);
        $this->assertSame(1, $notifications->getTotalCount());
        $this->assertStringContainsString('products.csv', $notifications->getNotifications()[0]->getTitle());

        $service->discard($fileId);
    }

    public function testANewObjectIsNotWrittenInALanguageTheWorkspaceDoesNotAllow(): void
    {
        $this->restrictUserToTheTarget(
            ['list' => true, 'view' => true, 'create' => true, 'save' => true, 'lEdit' => 'en']
        );
        $fileId = $this->uploadedFile();
        $service = $this->tester->grabService(AdHocImportRunServiceInterface::class);

        $service->start($fileId, $this->parameters([
            ['column' => 'sku', 'field' => 'sku'],
            ['column' => 'name', 'field' => 'title', 'language' => 'de'],
        ]));
        $this->drainQueue();

        $run = $service->status($fileId);
        $this->assertSame('done', $run->getState());
        $this->assertSame(0, $run->getCreated());
        $this->assertSame(2, $run->getFailed());
        $this->assertNull(DataObject::getByPath(self::FIRST_OBJECT), self::NOTHING_WRITTEN);

        $service->discard($fileId);
    }

    public function testARightTakenAwayAfterTheStartRefusesTheRowsAndWritesNothing(): void
    {
        $this->restrictUserToTheTarget(['list' => true, 'view' => true, 'create' => true, 'save' => true]);
        $fileId = $this->uploadedFile();
        $service = $this->tester->grabService(AdHocImportRunServiceInterface::class);
        $service->start($fileId, $this->parameters());

        $this->restrictUserToTheTarget(['list' => true, 'view' => true, 'create' => false, 'save' => true]);
        $this->drainQueue();

        $run = $service->status($fileId);
        $this->assertSame('done', $run->getState());
        $this->assertSame([0, 2], [$run->getCreated(), $run->getFailed()]);
        $this->assertSame(['create', 'create'], array_column($run->getFailures(), 'action'), 'refused per object');
        $this->assertNull(DataObject::getByPath(self::FIRST_OBJECT), self::NOTHING_WRITTEN);
        $this->assertNull(DataObject::getByPath(self::SECOND_OBJECT), self::NOTHING_WRITTEN);

        $service->discard($fileId);
    }

    public function testAnAdministratorDemotedDuringTheRunIsRefusedTheNextRow(): void
    {
        $fileId = $this->uploadedFile();
        $service = $this->tester->grabService(AdHocImportRunServiceInterface::class);
        $service->start($fileId, $this->parameters());
        [$firstRow, $secondRow] = $this->queuedIds();

        $this->processQueued($firstRow);
        $this->user->setAdmin(false);
        $this->user->save();
        $this->processQueued($secondRow);

        $run = $service->status($fileId);
        $this->assertSame('done', $run->getState());
        $this->assertSame([1, 1], [$run->getCreated(), $run->getFailed()]);
        $this->assertInstanceOf(Concrete::class, DataObject::getByPath(self::FIRST_OBJECT));
        $this->assertNull(DataObject::getByPath(self::SECOND_OBJECT), 'no longer the right to write it');

        $service->discard($fileId);
    }
}
