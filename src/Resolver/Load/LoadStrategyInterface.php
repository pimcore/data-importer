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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Load;

use Pimcore\Bundle\DataImporterBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface LoadStrategyInterface extends SettingsAwareInterface
{
    /**
     * Load element based on input data array
     *
     * @param array $inputData
     *
     * @return ElementInterface|null
     */
    public function loadElement(array $inputData): ?ElementInterface;

    /**
     * Load element based on given identifier (not whole input data array)
     *
     * @param string $identifier
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
     * @param string $dataObjectClassId
     */
    public function setDataObjectClassId($dataObjectClassId): void;
}
