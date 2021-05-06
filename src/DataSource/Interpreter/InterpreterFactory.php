<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;

class InterpreterFactory
{
    /**
     * @var InterpreterInterface[]
     */
    protected $interpreterBluePrints;

    /**
     * LoaderFactory constructor.
     *
     * @param InterpreterInterface[] $interpreterBluePrints
     */
    public function __construct(array $interpreterBluePrints)
    {
        $this->interpreterBluePrints = $interpreterBluePrints;
    }

    public function loadInterpreter(string $configName, array $interpreterConfiguration, array $processingConfiguration, Resolver $resolver = null)
    {
        if (empty($interpreterConfiguration['type']) || !array_key_exists($interpreterConfiguration['type'], $this->interpreterBluePrints)) {
            throw new InvalidConfigurationException('Unknown loader type `' . ($interpreterConfiguration['type'] ?? '') . '`');
        }

        $loader = clone $this->interpreterBluePrints[$interpreterConfiguration['type']];
        $loader->setConfigName($configName);
        $loader->setExecutionType($processingConfiguration['executionType'] ?? ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $loader->setIdDataIndex($processingConfiguration['idDataIndex'] ?? null);
        $loader->setDoDeltaCheck($processingConfiguration['doDeltaCheck'] ?? false);
        $loader->setDoCleanup($processingConfiguration['cleanup']['doCleanup'] ?? false);
        $loader->setDoArchiveImportFile($processingConfiguration['doArchiveImportFile'] ?? false);

        if ($resolver) {
            $loader->setResolver($resolver);
        }

        $loader->setSettings($interpreterConfiguration['settings'] ?? []);

        return $loader;
    }
}
