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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Load;

use Pimcore\Model\Element\ElementInterface;

class NotLoadStrategy implements LoadStrategyInterface
{
    public function loadElement(array $inputData): ?ElementInterface
    {
        return null;
    }

    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        return null;
    }

    public function extractIdentifierFromData(array $inputData)
    {
        return null;
    }

    public function loadFullIdentifierList(): array
    {
        return [];
    }

    public function setDataObjectClassId($dataObjectClassId): void
    {
        //nothing to do
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }
}
