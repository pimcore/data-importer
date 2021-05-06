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

namespace Pimcore\Bundle\DataImporterBundle\Command;

use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Console\AbstractCommand;
use Pimcore\Console\Traits\Parallelization;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParallelProcessQueueCommand extends AbstractCommand
{
    use Parallelization
    {
        Parallelization::runBeforeFirstCommand as parentRunBeforeFirstCommand;
        Parallelization::runAfterBatch as parentRunAfterBatch;
    }

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

    protected function configure()
    {
        self::configureParallelization($this);

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
     *
     * @param InputInterface $input The console input
     *
     * @return string[] The items to process
     */
    protected function fetchItems(InputInterface $input): array
    {
        return $this->queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_PARALLEL);
    }

    /**
     * Processes an item in the child process.
     */
    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        $this->importProcessingService->processQueueItem($item);
    }
}
