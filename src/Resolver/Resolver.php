<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Resolver;

use Pimcore\Bundle\DataImporterBundle\Resolver\Factory\FactoryInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\LocationStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\PublishStrategyInterface;
use Pimcore\Model\Element\ElementInterface;

class Resolver
{
    /**
     * @var string
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
     * @return string
     */
    public function getDataObjectClassId()
    {
        return $this->dataObjectClassId;
    }

    /**
     * @param string $dataObjectClassId
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
     * @param string $identifier
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
     * @return ElementInterface | null
     */
    public function loadOrCreateAndPrepareElement(array $inputData, bool $createNew = true): ?ElementInterface
    {
        $element = $this->loadElement($inputData);

        $justCreated = false;

        if (empty($element) && ! $createNew) {
            return null;
        }

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
