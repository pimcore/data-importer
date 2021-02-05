<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Load;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\ElementNotFoundException;
use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;

class AttributeStrategy extends AbstractLoad
{

    /**
     * @var string
     */
    protected $attributeName;

    /**
     * @var string
     */
    protected $attributeLanguage;

    /**
     * @param array $settings
     * @throws InvalidConfigurationException
     */
    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);

        if(empty($settings['attributeName'])) {
            throw new InvalidConfigurationException('Empty attribute name.');
        }

        $this->attributeName = $settings['attributeName'];
        $this->attributeLanguage = $settings['attributeLanguage'] ?? null;
    }

    /**
     * @param $identifier
     * @return ElementInterface|null
     * @throws InvalidConfigurationException
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        $className = $this->getClassName();
        $getter = 'getBy' . $this->attributeName;

        $element = null;
        if($this->attributeLanguage) {
            $element = $className::$getter($identifier, $this->attributeLanguage, 1);
        } else {
            $element = $className::$getter($identifier, 1);
        }

        if($element instanceof ElementInterface) {
            return $element;
        }

        return null;
    }

    /**
     * @return array
     */
    public function loadFullIdentifierList(): array
    {

        $tableName = 'object_' . $this->dataObjectClassId;
        if($this->attributeLanguage) {
            $tableName = 'object_localized_' . $this->dataObjectClassId . '_' . $this->attributeLanguage;
        }

        $sql = sprintf('SELECT `%s` FROM %s', $this->attributeName, $tableName);
        return $this->db->fetchCol($sql);
    }
}
