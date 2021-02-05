<?php

namespace Pimcore\Bundle\DataHubBatchImportBundle\EventListener;


use Pimcore\Bundle\DataHubBatchImportBundle\DataSource\Interpreter\DeltaChecker\DeltaChecker;
use Pimcore\Bundle\DataHubBatchImportBundle\Processing\Cron\CronExecutionService;
use Pimcore\Bundle\DataHubBatchImportBundle\Queue\QueueService;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Event\ConfigurationEvents;
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
     * ConfigurationEventSubscriber constructor.
     * @param DeltaChecker $deltaChecker
     * @param QueueService $queueService
     * @param CronExecutionService $cronExecutionService
     */
    public function __construct(DeltaChecker $deltaChecker, QueueService $queueService, CronExecutionService $cronExecutionService)
    {
        $this->deltaChecker = $deltaChecker;
        $this->queueService = $queueService;
        $this->cronExecutionService = $cronExecutionService;
    }


    public static function getSubscribedEvents()
    {
        return [
            ConfigurationEvents::CONFIGURATION_POST_DELETE => 'postDelete'
        ];
    }

    /**
     * @param GenericEvent $event
     * @throws \Doctrine\DBAL\DBALException
     */
    public function postDelete(GenericEvent $event) {
        /**
         * @var $config Configuration
         */
        $config = $event->getSubject();

        if($config->getType() === 'batchImportDataObject') {
            //cleanup delta cache
            $this->deltaChecker->cleanup($config->getName());

            //cleanup queue
            $this->queueService->cleanupQueueItems($config->getName());

            //cleanup preview files
            $folder = PIMCORE_PRIVATE_VAR . '/tmp/datahub/batchimport/' . $config->getName();
            recursiveDelete($folder);

            //cleanup cron execution
            $this->cronExecutionService->cleanup($config->getName());
        }
    }
}
