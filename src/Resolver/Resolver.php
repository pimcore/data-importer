<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Enterprise License (PEL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PEL
 */

namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver;

use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Factory\FactoryInterface;
use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Location\LocationStrategyInterface;
use Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Publish\PublishStrategyInterface;
use Pimcore\Model\Element\ElementInterface;

class Resolver
{
    /**
     * @var mixed
     */
    protected $dataObjectClassId;

    /**
     * @var LoadStrategyInterface
     */
    protected $loadingStrategy;

    /**
     * @var LocationStrategyInterface
     */
    protected $locationUpdateStrategy;

    /**
     * @var LocationStrategyInterface
     */
    protected $createLocationStrategy;

    /**
     * @var PublishStrategyInterface
     */
    protected $publishingStrategy;

    /**
     * @var FactoryInterface
     */
    protected $elementFactory;

    /**
     * @return mixed
     */
    public function getDataObjectClassId()
    {
        return $this->dataObjectClassId;
    }

    /**
     * @param mixed $dataObjectClassId
     */
    public function setDataObjectClassId($dataObjectClassId): void
    {
        $this->dataObjectClassId = $dataObjectClassId;
    }

    /**
     * @return LoadStrategyInterface
     */
    public function getLoadingStrategy(): LoadStrategyInterface
    {
        return $this->loadingStrategy;
    }

    /**
     * @param LoadStrategyInterface $loadingStrategy
     */
    public function setLoadingStrategy(LoadStrategyInterface $loadingStrategy): void
    {
        $this->loadingStrategy = $loadingStrategy;
    }

    /**
     * @return LocationStrategyInterface
     */
    public function getLocationUpdateStrategy(): LocationStrategyInterface
    {
        return $this->locationUpdateStrategy;
    }

    /**
     * @param LocationStrategyInterface $locationUpdateStrategy
     */
    public function setLocationUpdateStrategy(LocationStrategyInterface $locationUpdateStrategy): void
    {
        $this->locationUpdateStrategy = $locationUpdateStrategy;
    }

    /**
     * @return LocationStrategyInterface
     */
    public function getCreateLocationStrategy(): LocationStrategyInterface
    {
        return $this->createLocationStrategy;
    }

    /**
     * @param LocationStrategyInterface $createLocationStrategy
     */
    public function setCreateLocationStrategy(LocationStrategyInterface $createLocationStrategy): void
    {
        $this->createLocationStrategy = $createLocationStrategy;
    }

    /**
     * @return PublishStrategyInterface
     */
    public function getPublishingStrategy(): PublishStrategyInterface
    {
        return $this->publishingStrategy;
    }

    /**
     * @param PublishStrategyInterface $publishingStrategy
     */
    public function setPublishingStrategy(PublishStrategyInterface $publishingStrategy): void
    {
        $this->publishingStrategy = $publishingStrategy;
    }

    /**
     * @return FactoryInterface
     */
    public function getElementFactory(): FactoryInterface
    {
        return $this->elementFactory;
    }

    /**
     * @param FactoryInterface $elementFactory
     */
    public function setElementFactory(FactoryInterface $elementFactory): void
    {
        $this->elementFactory = $elementFactory;
    }

    /**
     * @param array $inputData
     *
     * @return ElementInterface|null
     */
    public function loadElement(array $inputData): ?ElementInterface
    {
        return $this->getLoadingStrategy()->loadElement($inputData);
    }

    /**
     * @param $identifier
     *
     * @return ElementInterface|null
     */
    public function loadElementByIdentifier($identifier): ?ElementInterface
    {
        return $this->getLoadingStrategy()->loadElementByIdentifier($identifier);
    }

    /**
     * @param array $inputData
     *
     * @return ElementInterface
     */
    public function loadOrCreateAndPrepareElement(array $inputData): ElementInterface
    {
        $element = $this->loadElement($inputData);

        $justCreated = false;
        if (empty($element)) {
            $element = $this->getElementFactory()->createNewElement();
            $this->getCreateLocationStrategy()->updateParent($element, $inputData);
            $justCreated = true;
        } else {
            $this->getLocationUpdateStrategy()->updateParent($element, $inputData);
        }

        $this->getPublishingStrategy()->updatePublishState($element, $justCreated, $inputData);

        return $element;
    }

    /**
     * @param array $inputData
     *
     * @return mixed
     */
    public function extractIdentifierFromData(array $inputData)
    {
        return $this->getLoadingStrategy()->extractIdentifierFromData($inputData);
    }

    /**
     * @return array
     */
    public function loadFullIdentifierList(): array
    {
        return $this->getLoadingStrategy()->loadFullIdentifierList();
    }
}
