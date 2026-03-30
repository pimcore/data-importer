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

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportPreparationService;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class CronExecutionCommand extends AbstractCommand
{
    public function __construct(
        private readonly ImportPreparationService $importPreparationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('datahub:data-importer:execute-cron')
            ->setDescription('Executes all data importer configurations corresponding to their cron definition.')
            ->addArgument('config_name', InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Names of configs that should be considered. Uses all if not specified.');
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
            $configNames = [];
            $allDataHubConfiguations = Configuration::getList();
            foreach ($allDataHubConfiguations as $dataHubConfig) {
                if (in_array($dataHubConfig->getType(), ['dataImporterDataObject'])) {
                    $configNames[] = $dataHubConfig->getName();
                }
            }
        }

        foreach ($configNames as $configName) {
            $output->writeln("Execution of config '$configName'");
            $this->importPreparationService->execute($configName);
        }

        return 0;
    }
}
