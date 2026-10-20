<?php declare(strict_types=1);

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

use Pimcore\Security\User\TokenStorageUserResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * AbstractInterpreter::processImportRow asks Admin::getCurrentUser() for the queue item owner, which needs a booted
 * kernel. This gives a unit test a kernel whose container reports "nobody logged in" (the CLI case) and removes it
 * again afterwards.
 */
trait NoCurrentUserKernelTrait
{
    private function installNoCurrentUserKernel(): void
    {
        $resolver = $this->createStub(TokenStorageUserResolver::class);
        $resolver->method('getUser')->willReturn(null);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($resolver);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        \Pimcore::setKernel($kernel);
    }

    private function removeKernel(): void
    {
        (new \ReflectionProperty(\Pimcore::class, 'kernel'))->setValue(null, null);
    }
}
