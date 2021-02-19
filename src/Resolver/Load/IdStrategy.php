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
use Pimcore\Model\Element\ElementInterface;

class IdStrategy extends AbstractLoad
{
    /**
     * @param $identifier
     *
     * @return ElementInterface|null
     *
     * @throws InvalidConfigurationException
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        $className = $this->getClassName();

        return $className::getById($identifier);
    }

    /**
     * @return array
     */
    public function loadFullIdentifierList(): array
    {
        $sql = sprintf('SELECT `o_id` FROM object_%s', $this->dataObjectClassId);

        return $this->db->fetchCol($sql);
    }
}
