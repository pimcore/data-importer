<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Publish;


use Pimcore\Model\Element\ElementInterface;

class NoChangeUnpublishNewStrategy implements PublishStrategyInterface
{

    public function setSettings(array $settings): void
    {
        //nothing to do
    }

    public function updatePublishState(ElementInterface $element, bool $justCreated, array $inputData): ElementInterface
    {
        if($justCreated) {
            $element->setPublished(false);
        }

        return $element;
    }


}
