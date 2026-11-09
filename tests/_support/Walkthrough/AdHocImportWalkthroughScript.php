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

use Pimcore\Bootstrap;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;

/**
 * The command line of run-ad-hoc-import-walkthrough.php, which says how to call it.
 */
final class AdHocImportWalkthroughScript
{
    private const int REFUSED = 2;

    /**
     * @param list<string> $arguments
     *
     * @return int the exit code
     */
    public function main(array $arguments): int
    {
        // before the application is loaded: a run that asks for a dry run never writes, whichever flag comes last
        $options = $this->options($arguments);
        if (is_string($options)) {
            fwrite(STDERR, $options . ": nothing was run.\n");

            return self::REFUSED;
        }

        echo sprintf("Target: application %s\n", getcwd());

        return $options['write'] ? $this->write($options) : $this->dryRun();
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{write: bool, way: ?string, transports: string, console: string}|string the options, or why not
     */
    private function options(array $arguments): array|string
    {
        $options = ['write' => false, 'way' => null, 'transports' => 'pimcore_data_import', 'console' => 'bin/console'];
        $dryRunAsked = false;
        foreach ($arguments as $argument) {
            if ($argument === '--write') {
                $options['write'] = true;
            } elseif ($argument === '--dry-run') {
                $dryRunAsked = true;
            } elseif (preg_match('/^--(way|transports|console)=(.+)$/', $argument, $option) === 1) {
                $options[$option[1]] = $option[2];
            } else {
                return sprintf('Unknown argument %s', $argument);
            }
        }

        return match (true) {
            $options['write'] && $dryRunAsked => '--dry-run and --write contradict each other',
            !in_array($options['way'], [null, 'direct', 'branch'], true) => '--way takes direct or branch',
            default => $options,
        };
    }

    private function dryRun(): int
    {
        echo "Ad hoc import walkthrough, dry run: nothing is booted, queued or saved; --write runs it\n\n";
        foreach (AdHocImportWalkthrough::plan() as $number => $step) {
            echo sprintf("%d %s\n", $number + 1, $step);
        }

        return 0;
    }

    /**
     * @param array{write: bool, way: ?string, transports: string, console: string} $options
     */
    private function write(array $options): int
    {
        require_once getcwd() . '/vendor/autoload.php';

        Bootstrap::setProjectRoot();
        Bootstrap::bootstrap();
        Bootstrap::startupCli();

        $container = \Pimcore::getContainer();
        $service = $container->get(AdHocImportRunServiceInterface::class);
        $params = $container->get('doctrine.dbal.default_connection')->getParams();
        echo sprintf(
            "Booted: database %s/%s\n",
            is_string($params['host'] ?? null) ? $params['host'] : '-',
            is_string($params['dbname'] ?? null) ? $params['dbname'] : '-'
        );

        $console = $options['console'];
        $walkthrough = new AdHocImportWalkthrough(
            $container->get(AdHocImportServiceInterface::class),
            $service,
            $container->get('pimcore.data_importer.ad_hoc_walkthrough.token_storage'),
            (new AdHocImportWalkthroughWork(
                static fn (string $fileId): string => $service->status($fileId)->getState(),
                static function (string $command) use ($console): array {
                    $output = [];
                    exec($console . ' ' . $command . ' 2>&1', $output, $exit);

                    return [$exit, implode("\n", $output)];
                },
                explode(',', $options['transports'])
            ))(...),
            AdHocImportWalkthrough::newRun(),
            $options['way'] === null ? null : $options['way'] === 'branch',
            true
        );
        echo sprintf("Ad hoc import walkthrough, run %s\n\n", $walkthrough->run);

        return $this->report($walkthrough);
    }

    private function report(AdHocImportWalkthrough $walkthrough): int
    {
        $failed = 0;

        try {
            foreach ($walkthrough->run() as $step) {
                echo $step->render(), "\n";
                $failed += $step->passed() ? 0 : 1;
            }
        } finally {
            // what the run made stays for inspection; its user must not stay usable
            $user = $walkthrough->user();
            $user?->setActive(false);
            $user?->save();
        }

        echo $failed === 0 ? "Every step PASS.\n" : sprintf("%d step(s) FAIL.\n", $failed);

        return $failed === 0 ? 0 : 1;
    }
}
