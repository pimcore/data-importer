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

use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * @internal
 */
final class NotLoadStrategy implements LoadStrategyInterface, SchemaAwareInterface
{
    public function loadElement(array $inputData): ?ElementInterface
    {
        return null;
    }

    /**
     * @param string $identifier
     *
     * @return ElementInterface|null
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        return null;
    }

    /**
     * @param array $inputData
     *
     * @return null
     */
    public function extractIdentifierFromData(array $inputData)
    {
        return null;
    }

    public function loadFullIdentifierList(): array
    {
        return [];
    }

    /**
     * @param string $dataObjectClassId
     *
     * @return void
     */
    public function setDataObjectClassId($dataObjectClassId): void
    {
        //nothing to do
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }

    public function getSchemaDescription(): string
    {
        return 'Does not load any existing objects - always creates new ones';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        // No configuration options - return null for better performance
        return null;
    }
}
