<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Saves each row right away, as the user. What was saved stays when a later row fails.
 *
 * @internal
 */
final readonly class DirectRowWriter implements RowWriterInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function open(RunRecord $run, User $user): RunRecord
    {
        // nothing to open: every row is saved on its own
        return $run;
    }

    public function write(RowWrite $write): void
    {
        $object = $write->object;
        $userId = $write->user->getId();
        $object->setUserModification($userId);
        if ($write->isNew) {
            $object->setUserOwner($userId);
        }
        $object->save();

        try {
            $this->eventDispatcher->dispatch(new PostSaveEvent(RowImporter::CONFIG_NAME, $write->row, $object));
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 the row is saved, whatever a listener does after that
            $this->logger->error(sprintf(
                'Ad hoc import, a listener failed after object %d was saved: %s',
                $object->getId(),
                $throwable->getMessage()
            ));
        }
    }

    public function close(RunRecord $run): array
    {
        return [];
    }
}
