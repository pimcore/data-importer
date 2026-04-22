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

    /**
     * @param string $configName
     * @param array $interpreterConfiguration
     * @param array $processingConfiguration
     * @param Resolver|null $resolver
     *
     * @return InterpreterInterface
     *
     * @throws InvalidConfigurationException
     */
    public function loadInterpreter(string $configName, array $interpreterConfiguration, array $processingConfiguration, ?Resolver $resolver = null)
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
