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

use Pimcore\Bundle\DataImporterBundle\Processing\ImportPreparationService;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class PrepareImportCommand extends AbstractCommand
{
    public function __construct(
        private readonly ImportPreparationService $importPreparationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('datahub:data-importer:prepare-import')
            ->setDescription('Loads and interprets data source file and prepares queue items for import.')
            ->addArgument('config_name', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Names of configs that should be considered.')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
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

        return 0;
    }
}
