<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\DataTarget;


use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface DataTargetInterface extends SettingsAwareInterface
{
    /**
     * @param ElementInterface $element
     * @param mixed $data
     * @return mixed
     */
    public function assignData(ElementInterface $element, $data);
}
