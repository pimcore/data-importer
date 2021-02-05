<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Location;


use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface LocationStrategyInterface extends SettingsAwareInterface
{

    /**
     * @param ElementInterface $element
     * @param array $inputData
     * @return ElementInterface
     */
    public function updateParent(ElementInterface $element, array $inputData): ElementInterface;

}
