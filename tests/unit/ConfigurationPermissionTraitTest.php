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
use Pimcore\Bundle\DataImporterBundle\Hydrator\ConfigurationDetailHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\ConfigurationService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConfigurationPermissionTraitTest extends Unit
{
    public function testAnUnknownConfigurationNameIsNotFound(): void
    {
        $hydrator = $this->createMock(ConfigurationDetailHydratorInterface::class);
        $hydrator->expects($this->never())->method('hydrate');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Configuration with name "no-such-configuration" not found');

        (new ConfigurationService($hydrator))->getConfiguration('no-such-configuration');
    }
}
