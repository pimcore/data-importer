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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Hydrator;

use Codeception\Test\Unit;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Hydrator\PreviewHydrator;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PreviewHydratorScopeTest extends Unit
{
    private const string CHANGE_SET = '01a08c7b-de2d-717b-93de-8aaee94a65a9';

    /** the stored configuration's upload never stands in for a change set's */
    public function testAScopedPreviewWithoutItsOwnUploadHasNoColumnHeaders(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('fileExists')
            ->with('car-import/_cs-' . self::CHANGE_SET . '-22.import')
            ->willReturn(false);
        $user = new User();
        $user->setId(22);
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($user);

        $hydrator = new PreviewHydrator(
            $security,
            new PreviewService($storage),
            new InterpreterFactory([]),
            new PreviewEventApplier(new EventDispatcher()),
        );

        static::assertSame(
            [],
            $hydrator->loadAvailableColumnHeaders('car-import', [], new PreviewScope(self::CHANGE_SET))
        );
    }
}
