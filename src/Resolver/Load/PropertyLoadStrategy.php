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
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Load;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\Element\ElementInterface;

class PropertyLoadStrategy extends AbstractLoad
{
    private string $propertyName;

    public function setSettings(array $settings): void
    {
        if (!array_key_exists('propertyName', $settings) || $settings['propertyName'] === null) {
            throw new \Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException('Empty propertyName.');
        }

        parent::setSettings($settings);

        $this->propertyName = $settings['propertyName'];
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
        $cidResults = $this->db->fetchAllAssociative('SELECT cid FROM properties WHERE name=? AND data=? AND ctype=?', [$this->propertyName, $identifier, 'object']);

        if (count($cidResults) == 0) {
            return null;
        }

        $cid = $cidResults[0]['cid'];
        return $this->dataObjectLoader->loadById($cid, $this->getClassName());
    }

    /**
     * @return array
     */
    public function loadFullIdentifierList(): array
    {
        return $this->db->fetchFirstColumn("SELECT data FROM properties WHERE name = ? AND ctype = ?", [$this->propertyName,  'object']);
    }
}
