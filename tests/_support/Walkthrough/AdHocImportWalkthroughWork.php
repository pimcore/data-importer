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

/**
 * Works off what an import queued on a real application, until the import no longer goes on.
 */
final readonly class AdHocImportWalkthroughWork
{
    private const string ROWS_COMMAND = 'datahub:data-importer:process-queue-sequential';

    /**
     * @param \Closure(string): string $stateOf the state of a file's import
     * @param \Closure(string, int): array{int, string} $console runs a console command for at most so many seconds: its exit code and output
     * @param list<string> $transports
     */
    public function __construct(
        private \Closure $stateOf,
        private \Closure $console,
        private array $transports,
        private int $timeout = 900,
    ) {
    }

    public function __invoke(string $fileId): void
    {
        $deadline = time() + $this->timeout;
        while ($this->goesOn($fileId)) {
            // the rows wait in the importer's queue: with messenger processing off, its default, only this takes them
            $this->run(self::ROWS_COMMAND . ' --no-interaction', $this->left($deadline, $fileId));
            if ($this->goesOn($fileId)) {
                $left = $this->left($deadline, $fileId);
                $this->run(sprintf(
                    'messenger:consume %s --limit=10 --time-limit=%d --no-interaction',
                    implode(' ', array_map(escapeshellarg(...), $this->transports)),
                    min(10, $left)
                ), $left);
            }
        }
    }

    private function left(int $deadline, string $fileId): int
    {
        $left = $deadline - time();
        if ($left <= 0) {
            throw new AdHocImportWalkthroughFailure(
                sprintf('The import of %s is still going on after %d s.', $fileId, $this->timeout)
            );
        }

        return $left;
    }

    private function goesOn(string $fileId): bool
    {
        return in_array(($this->stateOf)($fileId), ['running', 'merging'], true);
    }

    private function run(string $command, int $timeout): void
    {
        [$exit, $output] = ($this->console)($command, $timeout);
        if ($exit !== 0) {
            throw new AdHocImportWalkthroughFailure(sprintf("The worker failed (exit %d):\n%s", $exit, $output));
        }
    }
}
