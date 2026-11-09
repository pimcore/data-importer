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

class AdHocImportWalkthroughScriptTest extends Unit
{
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
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__) . '/_support/Walkthrough/run-ad-hoc-import-walkthrough.php', ...$arguments],
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
}
