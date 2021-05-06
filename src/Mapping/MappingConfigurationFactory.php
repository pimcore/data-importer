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

namespace Pimcore\Bundle\DataImporterBundle\Mapping;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\DataTargetInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\OperatorInterface;

class MappingConfigurationFactory
{
    /**
     * @var MappingConfiguration
     */
    protected $mappingConfigurationBluePrint;

    /**
     * @var OperatorInterface[]
     */
    protected $operatorBluePrints;

    /**
     * @var DataTargetInterface[]
     */
    protected $dataTargetBluePrints;

    /**
     * @param MappingConfiguration $mappingConfigurationBluePrint
     * @param OperatorInterface[] $operatorBluePrints
     * @param DataTargetInterface[] $dataTargetBluePrints
     */
    public function __construct(MappingConfiguration $mappingConfigurationBluePrint, array $operatorBluePrints, array $dataTargetBluePrints)
    {
        $this->mappingConfigurationBluePrint = $mappingConfigurationBluePrint;
        $this->operatorBluePrints = $operatorBluePrints;
        $this->dataTargetBluePrints = $dataTargetBluePrints;
    }

    /**
     * @param string $configName
     * @param array $configArray
     *
     * @return array
     *
     * @throws InvalidConfigurationException
     */
    protected function buildTransformationPipeline(string $configName, array $configArray): array
    {
        $transformationPipeline = [];

        foreach ($configArray as $config) {
            if (empty($config['type']) || !array_key_exists($config['type'], $this->operatorBluePrints)) {
                throw new InvalidConfigurationException('Unknown operator type `' . ($config['type'] ?? '') . '`');
            }

            $operator = clone $this->operatorBluePrints[$config['type']];
            $operator->setSettings(($config['settings'] ?? []));
            $operator->setConfigName($configName);

            $transformationPipeline[] = $operator;
        }

        return $transformationPipeline;
    }

    /**
     * @param array $config
     *
     * @return DataTargetInterface
     *
     * @throws InvalidConfigurationException
     */
    protected function buildDataTarget(array $config): DataTargetInterface
    {
        if (empty($config['type']) || !array_key_exists($config['type'], $this->dataTargetBluePrints)) {
            throw new InvalidConfigurationException('Unknown data target type `' . ($config['type'] ?? '') . '`');
        }

        $dataTarget = clone $this->dataTargetBluePrints[$config['type']];
        $dataTarget->setSettings($config['settings'] ?? []);

        return $dataTarget;
    }

    /**
     * @param string $configName
     * @param array $configurationArray
     * @param bool $ignoreDataTarget
     *
     * @return MappingConfiguration[]
     *
     * @throws InvalidConfigurationException
     */
    public function loadMappingConfiguration(string $configName, array $configurationArray, bool $ignoreDataTarget = false): array
    {
        $mappingConfigurationCollection = [];

        foreach ($configurationArray as $configurationEntry) {
            $mappingConfigurationCollection[] = $this->loadMappingConfigurationItem($configName, $configurationEntry, $ignoreDataTarget);
        }

        return $mappingConfigurationCollection;
    }

    /**
     * @param string $configName
     * @param array $configurationEntry
     * @param bool $ignoreDataTarget
     *
     * @return MappingConfiguration
     *
     * @throws InvalidConfigurationException
     */
    public function loadMappingConfigurationItem(string $configName, array $configurationEntry, bool $ignoreDataTarget = false): MappingConfiguration
    {
        $mappingConfiguration = clone $this->mappingConfigurationBluePrint;

        $mappingConfiguration->setLabel($configurationEntry['label'] ?? '');
        $mappingConfiguration->setDataSourceIndex($configurationEntry['dataSourceIndex'] ?? null);
        $mappingConfiguration->setTransformationPipeline($this->buildTransformationPipeline($configName, $configurationEntry['transformationPipeline'] ?? []));
        if (!$ignoreDataTarget) {
            $mappingConfiguration->setDataTarget($this->buildDataTarget($configurationEntry['dataTarget'] ?? []));
        }

        return $mappingConfiguration;
    }
}
