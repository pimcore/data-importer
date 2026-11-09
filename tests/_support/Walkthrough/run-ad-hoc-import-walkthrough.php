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
 * application writes the other way. The rows are worked off with datahub:data-importer:process-queue-sequential,
 * which also takes what other imports queued, and a merge with short `messenger:consume` runs of <transports>
 * (default pimcore_data_import), both started with <console> (default bin/console); a Change Control merge needs its
 * own transport named there or a worker running.
 *
 * What a run creates stays: its objects under "/Ad hoc import walkthrough/<run>", the class AdHocImportWalkthrough, and
 * its user, deactivated at the end. It exits non-zero if a step fails.
 */

use Pimcore\Bundle\DataImporterBundle\Tests\Walkthrough\AdHocImportWalkthroughScript;

require_once __DIR__ . '/WalkthroughStep.php';
require_once __DIR__ . '/AdHocImportWalkthrough.php';
require_once __DIR__ . '/AdHocImportWalkthroughWork.php';
require_once __DIR__ . '/AdHocImportWalkthroughScript.php';

exit((new AdHocImportWalkthroughScript())->main(array_slice($argv, 1))); // NOSONAR php:S1799 a command line script's exit code is its result
