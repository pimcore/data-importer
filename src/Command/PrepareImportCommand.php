<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\Command;

use Pimcore\Bundle\DataHubBatchImportBundle\Processing\ImportPreparationService;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PrepareImportCommand extends AbstractCommand
{
    /**
     * @var ImportPreparationService
     */
    protected $importPreparationService;

    /**
     * PrepareImportCommand constructor.
     *
     * @param ImportPreparationService $importPreparationService
     */
    public function __construct(ImportPreparationService $importPreparationService)
    {
        parent::__construct();
        $this->importPreparationService = $importPreparationService;
    }

    protected function configure()
    {
        $this
            ->setName('datahub:batch-import:prepare-import')
            ->setDescription('Loads and interprets data source file and prepares queue items for import.')
            ->addArgument('config_name', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Names of configs that should be considered.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configNames = $input->getArgument('config_name');

        if (empty($configNames)) {
            $output->writeln('No config given, nothing to do.');
        } else {
            foreach ($configNames as $configName) {
                $output->writeln("Preparing import for config '$configName'");
                $this->importPreparationService->prepareImport($configName);
            }
        }
    }
}
