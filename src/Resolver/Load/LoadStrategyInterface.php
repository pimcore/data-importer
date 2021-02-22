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

use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface LoadStrategyInterface extends SettingsAwareInterface
{
    /**
     * Load element based on input data array
     *
     * @param array $inputData
     *
     * @return ElementInterface
     */
    public function loadElement(array $inputData): ?ElementInterface;

    /**
     * Load element based on given identifier (not whole input data array)
     *
     * @param $identifier
     *
     * @return ElementInterface|null
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface;

    /**
     * Extract identifier from input data array
     *
     * @param array $inputData
     *
     * @return mixed
     */
    public function extractIdentifierFromData(array $inputData);

    /**
     * Load all in Pimcore existing identifiers (e.g. all data object IDs of certain data object class)
     *
     * @return array
     */
    public function loadFullIdentifierList(): array;

    /**
     * Set current data object class Id
     *
     * @param mixed $dataObjectClassId
     */
    public function setDataObjectClassId($dataObjectClassId): void;
}
