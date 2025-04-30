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

namespace Pimcore\Bundle\DataImporterBundle\Command;

use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParallelProcessQueueCommand extends ParallelizationAbstractCommand
{
    /**
     * @var ImportProcessingService
     */
    protected $importProcessingService;

    /**
     * @var QueueService
     */
    protected $queueService;

    public function __construct(ImportProcessingService $importProcessingService, QueueService $queueService)
    {
        parent::__construct();
        $this->importProcessingService = $importProcessingService;
        $this->queueService = $queueService;
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('datahub:data-importer:process-queue-parallel')
            ->setDescription('Processes all items of the queue that can be executed parallel.')
        ;
    }

    /**
     * Fetches the items that should be processed.
     *
     * Typically, you will fetch all the items of the database objects that
     * you want to process here. These will be passed to runSingleCommand().
     *
     * This method is called exactly once in the master process.
     */
    protected function doFetchItems(InputInterface $input, ?OutputInterface $output): array
    {
        return $this->queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_PARALLEL);
    }

    /**
     * Processes an item in the child process.
     */
    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        $this->importProcessingService->processQueueItem((int) $item);
    }
}
