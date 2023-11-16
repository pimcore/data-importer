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
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service as ElementService;
use Pimcore\Bundle\DataImporterBundle\Tool\ComposedPathBuilder;

class ComposedPathStrategy extends AbstractLoad
{

    private string $composedPath;

    /**
     * @param array $inputData
     *
     * @return ElementInterface|null
     *
     * @throws InvalidConfigurationException
     */
    public function loadElement(array $inputData): ?ElementInterface
    {
        $path = ComposedPathBuilder::buildPath($inputData, $this->composedPath);

        return $this->dataObjectLoader->loadByPath($path, $this->getClassName());
    }

    /**
     * @param string $identifier
     *
     * @return ElementInterface|null
     *
     * @throws InvalidConfigurationException
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        return $this->dataObjectLoader->loadByPath($identifier,
                                                   $this->getClassName());
    }

    /**
     * @return array
     */
    public function loadFullIdentifierList(): array
    {
        $sql = sprintf('SELECT CONCAT(`o_path`, `o_key`) FROM object_%s', $this->dataObjectClassId);

        return $this->db->fetchCol($sql);
    }


    public function setSettings(array $settings): void
    {
        if (!array_key_exists('composedPath', $settings) || $settings['composedPath'] === null) {
            throw new InvalidConfigurationException('Empty composed path.');
        }

        $this->composedPath = $settings['composedPath'];
    }
}
