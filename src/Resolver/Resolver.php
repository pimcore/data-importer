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

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Resolver\Factory\FactoryInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\LocationStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\PublishStrategyInterface;
use Pimcore\Model\DataObject\AbstractObject;
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

    public function getLoadingStrategy(): LoadStrategyInterface
    {
        return $this->loadingStrategy;
    }

    public function setLoadingStrategy(LoadStrategyInterface $loadingStrategy): void
    {
        $this->loadingStrategy = $loadingStrategy;
    }

    public function getLocationUpdateStrategy(): LocationStrategyInterface
    {
        return $this->locationUpdateStrategy;
    }

    public function setLocationUpdateStrategy(LocationStrategyInterface $locationUpdateStrategy): void
    {
        $this->locationUpdateStrategy = $locationUpdateStrategy;
    }

    public function getCreateLocationStrategy(): LocationStrategyInterface
    {
        return $this->createLocationStrategy;
    }

    public function setCreateLocationStrategy(LocationStrategyInterface $createLocationStrategy): void
    {
        $this->createLocationStrategy = $createLocationStrategy;
    }

    public function getPublishingStrategy(): PublishStrategyInterface
    {
        return $this->publishingStrategy;
    }

    public function setPublishingStrategy(PublishStrategyInterface $publishingStrategy): void
    {
        $this->publishingStrategy = $publishingStrategy;
    }

    public function getElementFactory(): FactoryInterface
    {
        return $this->elementFactory;
    }

    public function setElementFactory(FactoryInterface $elementFactory): void
    {
        $this->elementFactory = $elementFactory;
    }

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
     * @throws InvalidInputException
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
            $oldParentId = $element->getParentId();
            $this->getLocationUpdateStrategy()->updateParent($element, $inputData);

            // The parent of a variant cannot be changed anymore.
            if (
                $oldParentId !== $element->getParentId()
                && $element->getType() === AbstractObject::OBJECT_TYPE_VARIANT
            ) {
                throw new InvalidInputException(
                    "Element with id `{$element->getId()}` is a variant and cannot change its parent anymore"
                );
            }
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

    public function loadFullIdentifierList(): array
    {
        return $this->getLoadingStrategy()->loadFullIdentifierList();
    }
}
