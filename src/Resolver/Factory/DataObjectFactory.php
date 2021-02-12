<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Factory;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Factory;

class DataObjectFactory implements FactoryInterface
{

    /**
     * @var string
     */
    protected $subType;

    /**
     * @var Factory
     */
    protected $modelFactory;

    /**
     * @param Factory $modelFactory
     */
    public function __construct(Factory $modelFactory)
    {
        $this->modelFactory = $modelFactory;
    }

    /**
     * @param string $subType
     */
    public function setSubType(string $subType): void
    {
        $this->subType = $subType;
    }


    public function createNewElement(): ElementInterface
    {
        $class = ClassDefinition::getById($this->subType);
        if(empty($class)) {
            throw new InvalidConfigurationException("Class `{$this->subType}` not found.");
        }

        $className = '\\Pimcore\\Model\\DataObject\\' . $class->getName();
        $element = $this->modelFactory->build($className);

        $element->setKey(uniqid('import-', true));

        return $element;
    }

}
