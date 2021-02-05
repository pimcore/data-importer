<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Load;


use Pimcore\Bundle\DataHubBatchImportBundle\Settings\SettingsAwareInterface;
use Pimcore\Model\Element\ElementInterface;

interface LoadStrategyInterface extends SettingsAwareInterface
{

    /**
     * @param array $inputData
     * @return ElementInterface
     */
    public function loadElement(array $inputData): ?ElementInterface;

    /**
     * @param $identifier
     * @return ElementInterface|null
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface;

    /**
     * @param array $inputData
     * @return mixed
     */
    public function extractIdentifierFromData(array $inputData);

    /**
     * @return array
     */
    public function loadFullIdentifierList(): array;

    /**
     * @param mixed $dataObjectClassId
     */
    public function setDataObjectClassId($dataObjectClassId): void;

}
