<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Factory;


use Pimcore\Model\Element\ElementInterface;

interface FactoryInterface
{

    /**
     * @param string $subType
     */
    public function setSubType(string $subType): void;


    /**
     * @return ElementInterface
     */
    public function createNewElement(): ElementInterface;

}
