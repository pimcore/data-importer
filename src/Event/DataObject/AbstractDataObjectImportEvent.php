<?php


namespace Pimcore\Bundle\DataImporterBundle\Event\DataObject;

use Pimcore\Model\Element\ElementInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractDataObjectImportEvent extends Event
{

    /**
     * @var string
     */
    protected $configName;

    /**
     * @var array
     */
    protected $rawData;

    /**
     * @var ElementInterface
     */
    protected $dataObject;

    /**
     * AbstractDataObjectImportEvent constructor.
     * @param string $configName
     * @param array $rawData
     * @param ElementInterface $dataObject
     */
    public function __construct(string $configName, array $rawData, ElementInterface $dataObject)
    {
        $this->configName = $configName;
        $this->rawData = $rawData;
        $this->dataObject = $dataObject;
    }

    /**
     * @return string
     */
    public function getConfigName(): string
    {
        return $this->configName;
    }

    /**
     * @param string $configName
     */
    public function setConfigName(string $configName): AbstractDataObjectImportEvent
    {
        $this->configName = $configName;
        return $this;
    }

    /**
     * @return array
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * @param array $rawData
     */
    public function setRawData(array $rawData): AbstractDataObjectImportEvent
    {
        $this->rawData = $rawData;
        return $this;
    }

    /**
     * @return ElementInterface
     */
    public function getDataObject(): ElementInterface
    {
        return $this->dataObject;
    }

    /**
     * @param ElementInterface $dataObject
     */
    public function setDataObject(ElementInterface $dataObject): AbstractDataObjectImportEvent
    {
        $this->dataObject = $dataObject;
        return $this;
    }

}
