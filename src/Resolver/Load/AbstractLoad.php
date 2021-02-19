<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Load;

use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;

abstract class AbstractLoad implements LoadStrategyInterface
{
    /**
     * @var Db\Connection|Db\ConnectionInterface
     */
    protected $db;

    /**
     * @var mixed
     */
    protected $dataSourceIndex;

    /**
     * @var mixed
     */
    protected $dataObjectClassId;

    /**
     * AbstractLoad constructor.
     *
     * @param Db\ConnectionInterface $connection
     */
    public function __construct(Db\ConnectionInterface $connection)
    {
        $this->db = $connection;
    }

    public function setSettings(array $settings): void
    {
        if (!array_key_exists('dataSourceIndex', $settings) || $settings['dataSourceIndex'] === null) {
            throw new InvalidConfigurationException('Empty data source index.');
        }

        $this->dataSourceIndex = $settings['dataSourceIndex'];
    }

    /**
     * @param mixed $dataObjectClassId
     */
    public function setDataObjectClassId($dataObjectClassId): void
    {
        $this->dataObjectClassId = $dataObjectClassId;
    }

    protected function getClassName()
    {
        $class = ClassDefinition::getById($this->dataObjectClassId);
        if (empty($class)) {
            throw new InvalidConfigurationException("Class `{$this->dataObjectClassId}` not found.");
        }

        return '\\Pimcore\\Model\\DataObject\\' . $class->getName();
    }

    /**
     * @param array $inputData
     *
     * @return ElementInterface|null
     *
     * @throws InvalidConfigurationException
     */
    public function loadElement(array $inputData): ?ElementInterface
    {
        return $this->loadElementByIdentifier($this->extractIdentifierFromData($inputData));
    }

    /**
     * @param array $inputData
     *
     * @return mixed|null
     */
    public function extractIdentifierFromData(array $inputData)
    {
        return $inputData[$this->dataSourceIndex] ?? null;
    }
}
