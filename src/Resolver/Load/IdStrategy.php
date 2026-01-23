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

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Settings\SchemaAwareInterface;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class IdStrategy extends AbstractLoad implements SchemaAwareInterface
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
        $sql = sprintf(
            'SELECT `id` FROM object_%s',
            $this->dataObjectClassId
        );

        return $this->db->fetchFirstColumn($sql);
    }

    public function getSchemaDescription(): string
    {
        return 'Loads data objects by their numeric ID';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        return $this->getBaseConfigTreeBuilder();
    }
}
