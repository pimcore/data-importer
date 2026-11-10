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
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\PimcoreDataImporterExtension;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough\AdHocImportWalkthrough;
use Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough\AdHocImportWalkthroughConsole;
use Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough\AdHocImportWalkthroughFailure;
use Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough\AdHocImportWalkthroughWork;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AdHocImportWalkthroughScriptTest extends Unit
{
    private const string MESSENGER_PROCESSING = 'pimcore_data_importer.messenger_queue_processing.activated';

    /**
     * @param list<string> $arguments
     *
     * @return array{int, string, string} exit code, output and errors, from a directory with no application to boot
     */
    private function runScript(array $arguments): array
    {
        $cwd = sys_get_temp_dir() . '/' . uniqid('ad-hoc-walkthrough-script-', true);
        mkdir($cwd);

        try {
            $script = dirname(__DIR__) . '/_support/Walkthrough/run-ad-hoc-import-walkthrough.php';
            $process = proc_open(
                [PHP_BINARY, $script, ...$arguments],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $cwd
            );
            $this->assertIsResource($process);
            $output = (string) stream_get_contents($pipes[1]);
            $errors = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return [proc_close($process), $output, $errors];
        } finally {
            rmdir($cwd);
        }
    }

    public function testWithoutWriteItBootsNothingAndSaysWhatItWouldDo(): void
    {
        [$exit, $output] = $this->runScript([]);

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('dry run: nothing is booted', $output);
        $this->assertStringContainsString('a partial result', $output);
    }

    public function testDryRunWithWriteIsRefusedBeforeAnythingIsLoaded(): void
    {
        [$exit, $output, $errors] = $this->runScript(['--write', '--dry-run']);

        $this->assertSame(2, $exit);
        $this->assertSame('', $output);
        $this->assertStringContainsString('contradict each other', $errors);
    }

    public function testAnUnknownWayIsRefusedBeforeAnythingIsLoaded(): void
    {
        [$exit, $output, $errors] = $this->runScript(['--write', '--way=somewhere']);

        $this->assertSame(2, $exit);
        $this->assertSame('', $output);
        $this->assertStringContainsString('--way takes direct or branch', $errors);
    }

    public function testABootedKernelHandsTheScriptItsServices(): void
    {
        $container = \Pimcore::getContainer();

        $this->assertInstanceOf(
            AdHocImportServiceInterface::class,
            $container->get(AdHocImportServiceInterface::class)
        );
        $this->assertInstanceOf(
            AdHocImportRunServiceInterface::class,
            $container->get(AdHocImportRunServiceInterface::class)
        );
        $this->assertInstanceOf(
            TokenStorageInterface::class,
            $container->get('pimcore.data_importer.ad_hoc_walkthrough.token_storage')
        );
        $this->assertFalse($container->getParameter(self::MESSENGER_PROCESSING));
    }

    public function testTheApplicationTellsTheScriptWhetherItsWorkersTakeTheRows(): void
    {
        $this->assertFalse($this->loadedExtension([])->getParameter(self::MESSENGER_PROCESSING));
        $this->assertTrue(
            $this->loadedExtension(['messenger_queue_processing' => ['activated' => true]])
                ->getParameter(self::MESSENGER_PROCESSING)
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadedExtension(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/app');
        (new PimcoreDataImporterExtension())->load([$config], $container);

        return $container;
    }

    public function testRowsOfALaterRunCannotMatchTheObjectsOfAnEarlierOne(): void
    {
        $runs = array_map(static fn (): string => AdHocImportWalkthrough::newRun(), range(1, 5));
        $this->assertGreaterThan(1, count(array_unique($runs)), 'runs started within the same second differ');

        $this->assertSame('W-1-first', $this->walkthroughOfRun('first')->sku('W-1'));
        $this->assertSame('W-1-second', $this->walkthroughOfRun('second')->sku('W-1'));
    }

    private function walkthroughOfRun(string $run): AdHocImportWalkthrough
    {
        return new AdHocImportWalkthrough(
            $this->createMock(AdHocImportServiceInterface::class),
            $this->createMock(AdHocImportRunServiceInterface::class),
            $this->createMock(TokenStorageInterface::class),
            static fn (): null => null,
            $run,
            null
        );
    }

    /**
     * @param \Closure(string, int): array{int, string} $console
     */
    private function work(
        \Closure $stateOf,
        \Closure $console,
        int $timeout = 60,
        bool $messengerProcessing = false
    ): AdHocImportWalkthroughWork {
        return new AdHocImportWalkthroughWork($stateOf, $console, ['first', 'second'], $messengerProcessing, $timeout);
    }

    public function testRowsAreWorkedOffWhenMessengerProcessingIsOff(): void
    {
        $commands = [];
        $state = 'running';
        $work = $this->work(
            static function () use (&$state): string {
                return $state;
            },
            static function (string $command) use (&$commands, &$state): array {
                $commands[] = $command;
                // messenger processing is off: only the queue command takes the rows
                if (str_starts_with($command, 'datahub:data-importer:process-queue-sequential')) {
                    $state = 'done';
                }
                if (count($commands) > 3) {
                    throw new \LogicException('Nothing takes the rows.');
                }

                return [0, ''];
            }
        );

        $work('file');

        $this->assertSame(['datahub:data-importer:process-queue-sequential --no-interaction'], $commands);
    }

    public function testRowsAreLeftToTheWorkersWhenMessengerProcessingIsOn(): void
    {
        $commands = [];
        $state = 'running';
        $work = $this->work(
            static function () use (&$state): string {
                return $state;
            },
            static function (string $command) use (&$commands, &$state): array {
                $commands[] = $command;
                // the second consume run takes the last of the rows
                if (count($commands) === 2) {
                    $state = 'done';
                }
                if (count($commands) > 3) {
                    throw new \LogicException('The work does not end.');
                }

                return [0, ''];
            },
            messengerProcessing: true
        );

        $work('file');

        $consume = "messenger:consume 'first' 'second' --limit=10 --time-limit=10 --no-interaction";
        $this->assertSame([$consume, $consume], $commands);
    }

    public function testAMergeIsWorkedOffByItsTransports(): void
    {
        $commands = [];
        $state = 'running';
        $work = $this->work(
            static function () use (&$state): string {
                return $state;
            },
            static function (string $command) use (&$commands, &$state): array {
                $commands[] = $command;
                $state = str_starts_with($command, 'messenger:consume') ? 'decision' : 'merging';

                return [0, ''];
            }
        );

        $work('file');

        $this->assertCount(2, $commands);
        $this->assertSame(
            "messenger:consume 'first' 'second' --limit=10 --time-limit=10 --no-interaction",
            $commands[1]
        );
    }

    public function testACommandThatFailsEndsTheWorkWithItsOutput(): void
    {
        $work = $this->work(static fn (): string => 'running', static fn (): array => [3, 'it went wrong']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("exit 3):\nit went wrong");

        $work('file');
    }

    public function testWorkThatDoesNotEndGivesUp(): void
    {
        $work = $this->work(static fn (): string => 'running', static fn (): array => [0, ''], 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('still going on after 0 s');

        $work('file');
    }

    public function testEveryCommandGetsOnlyWhatIsLeftOfTheTimeLimit(): void
    {
        $limits = [];
        $state = 'running';
        $work = $this->work(
            static function () use (&$state): string {
                return $state;
            },
            static function (string $command, int $limit) use (&$limits, &$state): array {
                $limits[] = $limit;
                $state = str_starts_with($command, 'messenger:consume') ? 'done' : 'merging';
                if (count($limits) === 1) {
                    sleep(2);
                }

                return [0, ''];
            }
        );

        $work('file');

        $this->assertCount(2, $limits);
        $this->assertGreaterThanOrEqual(59, $limits[0]);
        $this->assertLessThanOrEqual(58, $limits[1]);
    }

    public function testNoWorkerStartsOnceTheTimeLimitIsSpent(): void
    {
        $commands = [];
        $work = $this->work(
            static fn (): string => 'running',
            static function (string $command) use (&$commands): array {
                $commands[] = $command;
                sleep(2);

                return [0, ''];
            },
            1
        );

        try {
            $work('file');
            $this->fail('The work did not give up.');
        } catch (AdHocImportWalkthroughFailure $failure) {
            $this->assertStringContainsString('still going on after 1 s', $failure->getMessage());
        }

        $this->assertCount(1, $commands);
    }

    public function testAConsoleCommandGivesItsExitCodeAndAllItsOutput(): void
    {
        $console = new AdHocImportWalkthroughConsole("sh -c 'echo out; echo err >&2; exit 3' --");

        $this->assertSame([3, "out\nerr\n"], $console('ignored', 10));
    }

    public function testAWorkerPastItsTimeLimitIsStoppedAndReported(): void
    {
        $directory = sys_get_temp_dir() . '/' . uniqid('ad-hoc-walkthrough-console-', true);
        mkdir($directory);
        $worker = $directory . '/worker.sh';
        $pidFile = $directory . '/pid';
        file_put_contents($worker, sprintf("echo started\necho $$ > %s\nexec sleep 60\n", escapeshellarg($pidFile)));

        try {
            $started = microtime(true);
            (new AdHocImportWalkthroughConsole('sh ' . escapeshellarg($worker)))('ignored', 1);
            $this->fail('The worker was not stopped.');
        } catch (AdHocImportWalkthroughFailure $failure) {
            $this->assertLessThan(30, microtime(true) - $started);
            $this->assertStringContainsString('stopped after 1 s', $failure->getMessage());
            $this->assertStringContainsString('started', $failure->getMessage());
            $this->assertDirectoryDoesNotExist('/proc/' . trim((string) file_get_contents($pidFile)));
        } finally {
            array_map(unlink(...), (array) glob($directory . '/*'));
            rmdir($directory);
        }
    }
}
