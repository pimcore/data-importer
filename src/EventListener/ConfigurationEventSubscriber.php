<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\EventListener;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Event\ConfigurationEvents;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataImporterBundle\Processing\Cron\CronExecutionService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Logger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface as EventSubscriberInterfaceAlias;
use Symfony\Component\EventDispatcher\GenericEvent;

class ConfigurationEventSubscriber implements EventSubscriberInterfaceAlias
{
    /**
     * @var DeltaChecker
     */
    protected $deltaChecker;

    /**
     * @var QueueService
     */
    protected $queueService;

    /**
     * @var CronExecutionService
     */
    protected $cronExecutionService;

    /**
     * @var FilesystemOperator
     */
    protected FilesystemOperator $pimcoreDataImporterUploadStorage;

    /**
     * @var FilesystemOperator
     */
    protected FilesystemOperator $pimcoreDataImporterPreviewStorage;

    /**
     * @param DeltaChecker $deltaChecker
     * @param QueueService $queueService
     * @param CronExecutionService $cronExecutionService
     * @param FilesystemOperator $pimcoreDataImporterUploadStorage
     * @param FilesystemOperator $pimcoreDataImporterPreviewStorage
     */
    public function __construct(DeltaChecker $deltaChecker, QueueService $queueService, CronExecutionService $cronExecutionService, FilesystemOperator $pimcoreDataImporterUploadStorage, FilesystemOperator $pimcoreDataImporterPreviewStorage)
    {
        $this->deltaChecker = $deltaChecker;
        $this->queueService = $queueService;
        $this->cronExecutionService = $cronExecutionService;
        $this->pimcoreDataImporterUploadStorage = $pimcoreDataImporterUploadStorage;
        $this->pimcoreDataImporterPreviewStorage = $pimcoreDataImporterPreviewStorage;
    }

    public static function getSubscribedEvents()
    {
        return [
            ConfigurationEvents::CONFIGURATION_POST_DELETE => 'postDelete'
        ];
    }

    /**
     * @param GenericEvent $event
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function postDelete(GenericEvent $event)
    {
        /**
         * @var $config Configuration
         */
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
            $this->cronExecutionService->cleanup($config->getName());
        }
    }
}
