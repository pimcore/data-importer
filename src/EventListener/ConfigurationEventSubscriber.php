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

namespace Pimcore\Bundle\DataImporterBundle\EventListener;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Event\ConfigurationEvents;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\Processing\ExecutionService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Logger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface as EventSubscriberInterfaceAlias;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @internal
 */
final class ConfigurationEventSubscriber implements EventSubscriberInterfaceAlias
{
    public function __construct(
        private readonly DeltaChecker $deltaChecker,
        private readonly QueueService $queueService,
        private readonly ExecutionService $executionService,
        private readonly FilesystemOperator $pimcoreDataImporterUploadStorage,
        private readonly FilesystemOperator $pimcoreDataImporterPreviewStorage,
    ) {
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigurationEvents::CONFIGURATION_POST_DELETE => 'postDelete',
            ConfigurationEvents::CONFIGURATION_POST_SAVE => 'postSave'
        ];
    }

    /**
     * @param GenericEvent $event
     */
    public function postDelete(GenericEvent $event)
    {
        /** @var Configuration $config */
        $config = $event->getSubject();

        if ($config->getType() === 'dataImporterDataObject') {
            //cleanup delta cache
            $this->deltaChecker->cleanup($config->getName());

            //cleanup queue
            $this->queueService->cleanupQueueItems($config->getName());

            //cleanup preview files
            try {
                $this->pimcoreDataImporterPreviewStorage->deleteDirectory($config->getName());
            } catch (FilesystemException $e) {
                Logger::info($e);
            }

            //cleanup upload files
            try {
                $this->pimcoreDataImporterUploadStorage->deleteDirectory($config->getName());
            } catch (FilesystemException $e) {
                Logger::info($e);
            }

            //cleanup cron execution
            $this->executionService->cleanup($config->getName());
        }
    }

    public function postSave(GenericEvent $event)
    {
        /** @var Configuration $config */
        $config = $event->getSubject();

        if ($config->getType() === 'dataImporterDataObject') {
            $this->executionService->initExecution($config->getName());
        }
    }
}
