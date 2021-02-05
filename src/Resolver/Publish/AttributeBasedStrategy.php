<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Publish;


use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\Element\ElementInterface;

class AttributeBasedStrategy implements PublishStrategyInterface
{

    /**
     * @var mixed
     */
    protected $dataSourceIndex;

    public function setSettings(array $settings): void
    {
        if(empty($settings['dataSourceIndex'])) {
            throw new InvalidConfigurationException('Empty data source index.');
        }

        $this->dataSourceIndex = $settings['dataSourceIndex'];
    }

    public function updatePublishState(ElementInterface $element, bool $justCreated, array $inputData): ElementInterface
    {
        $element->setPublished($inputData[$this->dataSourceIndex] ?? false);

        return $element;
    }


}
