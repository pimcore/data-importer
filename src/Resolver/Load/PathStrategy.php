<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Load;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\ElementNotFoundException;
use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;

class PathStrategy extends AbstractLoad
{

    /**
     * @param $identifier
     * @return ElementInterface|null
     * @throws InvalidConfigurationException
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        $className = $this->getClassName();
        return $className::getByPath($identifier);
    }

    /**
     * @return array
     */
    public function loadFullIdentifierList(): array
    {
        $sql = sprintf('SELECT CONCAT(`o_path`, `o_key`) FROM object_%s', $this->dataObjectClassId);
        return $this->db->fetchCol($sql);
    }
}
