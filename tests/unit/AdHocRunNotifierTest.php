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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunNotifier;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Notification\Service\SendNotificationServiceInterface;
use Psr\Log\NullLogger;

class AdHocRunNotifierTest extends Unit
{
    public function testANotificationThatCannotGoOutDoesNotUndoTheRun(): void
    {
        $notifications = $this->createStub(SendNotificationServiceInterface::class);
        $notifications->method('sendNotification')->willThrowException(new ForbiddenException('no'));

        (new RunNotifier($notifications, AdHocRunFixtures::runMessages(), new NullLogger()))
            ->notify(AdHocRunFixtures::run(), [], AdHocRunFixtures::user());

        $this->addToAssertionCount(1);
    }
}
