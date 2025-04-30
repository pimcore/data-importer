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

namespace Pimcore\Bundle\DataImporterBundle\Maintenance;

use Pimcore\Bundle\DataImporterBundle\Messenger\DataImporterHandler;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Maintenance\TaskInterface;

class RestartQueueWorkersTask implements TaskInterface
{
    public function __construct(
        protected DataImporterHandler $dataImporterHandler,
        protected bool $messengerQueueActivated
    ) {
    }

    public function execute(): void
    {
        if ($this->messengerQueueActivated === true) {
            $this->dataImporterHandler->dispatchMessages(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
            $this->dataImporterHandler->dispatchMessages(ImportProcessingService::EXECUTION_TYPE_PARALLEL);
        }
    }
}
