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

use Pimcore\Console\AbstractCommand;
use Pimcore\Console\Traits\Parallelization;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
abstract class ParallelizationAbstractCommand extends AbstractCommand
{
    use Parallelization
    {
        Parallelization::runBeforeFirstCommand as parentRunBeforeFirstCommand;
        Parallelization::runAfterBatch as parentRunAfterBatch;
    }

    protected function configure(): void
    {
        self::configureCommand($this);
    }

    abstract protected function doFetchItems(InputInterface $input, ?OutputInterface $output): array;

    protected function fetchItems(InputInterface $input, OutputInterface $output): array
    {
        return $this->doFetchItems($input, $output);
    }
}
