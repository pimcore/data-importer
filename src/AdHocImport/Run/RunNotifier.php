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

use Pimcore\Bundle\StudioBackendBundle\Notification\Schema\SendNotificationParameters;
use Pimcore\Bundle\StudioBackendBundle\Notification\Service\SendNotificationServiceInterface;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;

/**
 * Tells the user that a run ended, whether or not its dialog is still open.
 *
 * @internal
 */
final readonly class RunNotifier
{
    public function __construct(
        private SendNotificationServiceInterface $notifications,
        private RunMessages $messages,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * A notification that cannot go out must not undo the run: the result stays in the dialog.
     *
     * @param list<RowFailure> $failures
     */
    public function notify(RunRecord $run, array $failures, User $user): void
    {
        try {
            // no sender: Studio does not notify a user of what they sent themselves
            $this->notifications->sendNotification(new SendNotificationParameters(
                $user->getId(),
                $this->messages->notificationTitle($run, $user),
                $this->messages->notificationText($run, $failures, $user)
            ), null);
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf('Ad hoc import of %s, notification: %s', $run->fileId, $exception->getMessage())
            );
        }
    }
}
