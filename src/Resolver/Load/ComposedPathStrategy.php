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

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Tool\ComposedPathBuilder;

class ComposedPathStrategy extends PathStrategy
{
    private string $composedPath;

    public function setSettings(array $settings): void
    {
        if (!array_key_exists('composedPath', $settings) || $settings['composedPath'] === null) {
            throw new InvalidConfigurationException('Empty composed path.');
        }

        $this->composedPath = $settings['composedPath'];
    }

    /**
     * @param array $inputData
     *
     * @return mixed
     */
    public function extractIdentifierFromData(array $inputData): string
    {
        return ComposedPathBuilder::buildPath($inputData, $this->composedPath);
    }
}
