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
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ElementInterface;

class IdStrategy extends AbstractLoad
{
    /**
     * @param string $identifier
     *
     * @return ElementInterface|null
     *
     * @throws InvalidConfigurationException
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        return $this->dataObjectLoader->loadById($identifier,
                                                 $this->getClassName());
    }

    public function loadFullIdentifierList(): array
    {
        $sql = sprintf('SELECT `%s` FROM object_%s', Service::getVersionDependentDatabaseColumnName('o_id'), $this->dataObjectClassId);

        return $this->db->fetchFirstColumn($sql);
    }
}
