<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    define('PIMCORE_PROJECT_ROOT', __DIR__);
} elseif (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    define('PIMCORE_PROJECT_ROOT', __DIR__ . '/../../..');
} elseif (getenv('PIMCORE_PROJECT_ROOT')) {
    define('PIMCORE_PROJECT_ROOT', getenv('PIMCORE_PROJECT_ROOT'));
} else {
    throw new \InvalidArgumentException(
        'Unknown configuration! Pimcore project root not found, please set env variable PIMCORE_PROJECT_ROOT.'
    );
}

include PIMCORE_PROJECT_ROOT . '/vendor/autoload.php';
\Pimcore\Bootstrap::setProjectRoot();
\Pimcore\Bootstrap::bootstrap();

if (!defined('PIMCORE_TEST')) {
    define('PIMCORE_TEST', true);
}
