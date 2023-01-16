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

use Pimcore\Console\AbstractCommand;
use Pimcore\Console\Traits\Parallelization;
use Pimcore\Version;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//BC layer to support Pimcore 10
//TODO remove when remove support for Pimcore 10
if (Version::getMajorVersion() >= 11) {
    abstract class ParallelizationAbstractCommand extends AbstractCommand
    {
        use Parallelization
        {
            Parallelization::runBeforeFirstCommand as parentRunBeforeFirstCommand;
            Parallelization::runAfterBatch as parentRunAfterBatch;
        }

        protected function configure()
        {
            self::configureCommand($this);
        }

        abstract protected function doFetchItems(InputInterface $input, ?OutputInterface $output): array;

        protected function fetchItems(InputInterface $input, OutputInterface $output): array
        {
            return $this->doFetchItems($input, $output);
        }
    }
} else {
    abstract class ParallelizationAbstractCommand extends AbstractCommand
    {
        use Parallelization
        {
            Parallelization::runBeforeFirstCommand as parentRunBeforeFirstCommand;
            Parallelization::runAfterBatch as parentRunAfterBatch;
        }

        abstract protected function doFetchItems(InputInterface $input, ?OutputInterface $output): array;

        protected function configure()
        {
            self::configureParallelization($this);
        }

        protected function fetchItems(InputInterface $input): array
        {
            return $this->doFetchItems($input, null);
        }
    }
}
