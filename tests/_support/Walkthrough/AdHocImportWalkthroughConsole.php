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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs a console command of the application, for no longer than it is given.
 */
final readonly class AdHocImportWalkthroughConsole
{
    public function __construct(private string $console)
    {
    }

    /**
     * @return array{int, string} the exit code and the output
     */
    public function __invoke(string $command, int $timeout): array
    {
        // exec: the stop has to reach the worker, not a shell around it
        $process = Process::fromShellCommandline(
            'exec ' . $this->console . ' ' . $command . ' 2>&1',
            null,
            null,
            null,
            $timeout
        );

        try {
            return [$process->run(), $process->getOutput()];
        } catch (ProcessTimedOutException) {
            throw new AdHocImportWalkthroughFailure(
                sprintf("The worker was stopped after %d s:\n%s", $timeout, $process->getOutput())
            );
        }
    }
}
