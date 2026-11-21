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

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Command\SequentialProcessQueueCommand;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * A run that finds the command already running must end like any other run: exit code 0 and the
 * console.terminate event dispatched (exit() would skip it, so cron monitors never see the run end).
 */
class SequentialProcessQueueCommandTest extends Unit
{
    private const COMMAND_NAME = 'datahub:data-importer:process-queue-sequential';

    private ?KernelInterface $previousKernel = null;

    private string $lockDir;

    protected function _before(): void
    {
        $this->lockDir = sys_get_temp_dir() . '/pme_data_importer_lock_' . bin2hex(random_bytes(4));
        mkdir($this->lockDir);
    }

    protected function _after(): void
    {
        if ($this->previousKernel !== null) {
            (new \ReflectionProperty(\Pimcore::class, 'kernel'))->setValue(null, $this->previousKernel);
            $this->previousKernel = null;
        }

        (new Filesystem())->remove($this->lockDir);
    }

    public function testAlreadyRunningEndsWithSuccessAndDispatchesTerminate(): void
    {
        $lockFactory = new LockFactory(new FlockStore($this->lockDir));
        $this->installKernel($lockFactory);

        // another run of the command holds the lock
        $held = $lockFactory->createLock(self::COMMAND_NAME, 60);
        $this->assertTrue($held->acquire(false));

        // both services are final and left uninitialized: any use of the queue would throw
        $queueService = (new \ReflectionClass(QueueService::class))
            ->newInstanceWithoutConstructor();
        $importProcessingService = (new \ReflectionClass(ImportProcessingService::class))
            ->newInstanceWithoutConstructor();

        $terminateExitCodes = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ConsoleEvents::TERMINATE,
            static function (ConsoleTerminateEvent $event) use (&$terminateExitCodes): void {
                $terminateExitCodes[] = $event->getExitCode();
            }
        );

        $application = new Application();
        $application->setAutoExit(false);
        $application->setDispatcher($dispatcher);
        $application->add(new SequentialProcessQueueCommand($importProcessingService, $queueService));

        $output = new BufferedOutput();

        try {
            $exitCode = $application->run(new ArrayInput(['command' => self::COMMAND_NAME]), $output);
        } finally {
            $held->release();
        }

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('The command is already running.', $output->fetch());
        $this->assertSame([0], $terminateExitCodes, 'console.terminate is dispatched once, with exit code 0');
    }

    private function installKernel(LockFactory $lockFactory): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($lockFactory);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $this->previousKernel = (new \ReflectionProperty(\Pimcore::class, 'kernel'))->getValue();
        \Pimcore::setKernel($kernel);
    }
}
