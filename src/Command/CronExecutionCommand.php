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

use Pimcore\Bundle\DataHubBundle\Configuration\Dao;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportPreparationService;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CronExecutionCommand extends AbstractCommand
{
    /**
     * @var ImportPreparationService
     */
    protected $importPreparationService;

    public function __construct(ImportPreparationService $importPreparationService)
    {
        parent::__construct();
        $this->importPreparationService = $importPreparationService;
    }

    protected function configure()
    {
        $this
            ->setName('datahub:data-importer:execute-cron')
            ->setDescription('Executes all data importer configurations corresponding to their cron definition.')
            ->addArgument('config_name', InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Names of configs that should be considered. Uses all if not specified.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configNames = $input->getArgument('config_name');

        if (empty($configNames)) {
            $configNames = [];
            $allDataHubConfiguations = Dao::getList();
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
