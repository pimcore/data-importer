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

namespace Pimcore\Bundle\DataImporterBundle\EventListener;

use Pimcore\Bundle\DataImporterBundle\Event\PostPreparationEvent;
use Pimcore\Bundle\DataImporterBundle\Messenger\DataImporterHandler;

/**
 * @internal
 */
final readonly class DataImporterListener
{
    public function __construct(
        private DataImporterHandler $dataImporterHandler,
        private bool $messengerQueueActivated,
    ) {
    }

    public function importPrepared(PostPreparationEvent $event)
    {
        if (!$this->messengerQueueActivated) {
            return;
        }

        $this->dataImporterHandler->dispatchMessages($event->getExecutionType());
    }
}
