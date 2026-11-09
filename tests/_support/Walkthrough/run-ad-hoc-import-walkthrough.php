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

/*
 * The ad hoc import walkthrough against a running application, from its project root:
 *
 *   php <bundle checkout>/tests/_support/Walkthrough/run-ad-hoc-import-walkthrough.php
 *   php <bundle checkout>/tests/_support/Walkthrough/run-ad-hoc-import-walkthrough.php --write [--way=direct|branch] [--transports=pimcore_data_import,...] [--console=bin/console]
 *
 * Without --write it is a dry run: it prints what a run would do and exits without booting the application or changing
 * anything. --dry-run together with --write is refused before anything is loaded.
 *
 * It imports a few files as a user the run creates, through the services the Studio API calls, and reads every result
 * back from the database. The application writes the way it is set up: directly, or through a Change Control branch
 * with Change Control installed on a core that ships its contract. --way=direct or --way=branch fails the run if the
 * application writes the other way. The rows and a merge are worked off with short `messenger:consume` runs of
 * <transports> (default pimcore_data_import), started with <console> (default bin/console); a Change Control merge
 * needs its own transport named there or a worker running.
 *
 * What a run creates stays: its objects under "/Ad hoc import walkthrough/<run>", the class AdHocImportWalkthrough, and
 * its user, deactivated at the end. It exits non-zero if a step fails.
 */

use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough\AdHocImportWalkthrough;

$options = ['way' => null, 'transports' => 'pimcore_data_import', 'console' => 'bin/console'];
$write = false;
$dryRunAsked = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--write') {
        $write = true;
    } elseif ($arg === '--dry-run') {
        $dryRunAsked = true;
    } elseif (preg_match('/^--(way|transports|console)=(.+)$/', $arg, $option) === 1) {
        $options[$option[1]] = $option[2];
    } else {
        fwrite(STDERR, sprintf("Unknown argument %s: nothing was run.\n", $arg));

        exit(2);
    }
}
// before anything is loaded: a run that asks for a dry run never writes, whichever flag comes last
if ($write && $dryRunAsked) {
    fwrite(STDERR, "--dry-run and --write contradict each other: nothing was run.\n");

    exit(2);
}
if (!in_array($options['way'], [null, 'direct', 'branch'], true)) {
    fwrite(STDERR, "--way takes direct or branch: nothing was run.\n");

    exit(2);
}

require_once __DIR__ . '/WalkthroughStep.php';
require_once __DIR__ . '/AdHocImportWalkthrough.php';

echo sprintf("Target: application %s\n", getcwd());

if (!$write) {
    echo "Ad hoc import walkthrough, dry run: nothing is booted, queued or saved; --write runs it\n\n";
    foreach (AdHocImportWalkthrough::plan() as $number => $step) {
        echo sprintf("%d %s\n", $number + 1, $step);
    }

    exit(0);
}

require getcwd() . '/vendor/autoload.php';

Pimcore\Bootstrap::setProjectRoot();
Pimcore\Bootstrap::bootstrap();
Pimcore\Bootstrap::startupCli();

$container = Pimcore::getContainer();
$service = $container->get(AdHocImportRunServiceInterface::class);
$params = $container->get('doctrine.dbal.default_connection')->getParams();
echo sprintf("Booted: database %s/%s\n", is_string($params['host'] ?? null) ? $params['host'] : '-', is_string($params['dbname'] ?? null) ? $params['dbname'] : '-');

$console = $options['console'];
$transports = implode(' ', array_map(escapeshellarg(...), explode(',', $options['transports'])));
$work = static function (string $fileId) use ($service, $console, $transports): void {
    $deadline = time() + 900;
    while (in_array($service->status($fileId)->getState(), ['running', 'merging'], true)) {
        $left = $deadline - time();
        if ($left <= 0) {
            throw new RuntimeException(sprintf('The import of %s is still going on after 900 s.', $fileId));
        }
        $output = [];
        exec($console . ' messenger:consume ' . $transports . ' --limit=10 --time-limit=' . min(10, $left) . ' --no-interaction 2>&1', $output, $exit);
        if ($exit !== 0) {
            throw new RuntimeException(sprintf("The worker failed (exit %d):\n%s", $exit, implode("\n", $output)));
        }
    }
};

$walkthrough = new AdHocImportWalkthrough(
    $container->get(AdHocImportServiceInterface::class),
    $service,
    $container->get('pimcore.data_importer.ad_hoc_walkthrough.token_storage'),
    $work,
    AdHocImportWalkthrough::newRun(),
    $options['way'] === null ? null : $options['way'] === 'branch',
    true
);
echo sprintf("Ad hoc import walkthrough, run %s\n\n", $walkthrough->run);

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

exit($failed === 0 ? 0 : 1);
