<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Location;


use Pimcore\Model\Element\ElementInterface;

class NoChangeStrategy implements LocationStrategyInterface
{

    public function updateParent(ElementInterface $element, array $inputData): ElementInterface
    {
        return $element;
    }

    public function setSettings(array $settings): void
    {
        //nothing to do
    }
}
