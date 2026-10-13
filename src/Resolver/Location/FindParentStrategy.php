<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Location;

use Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class FindParentStrategy implements LocationStrategyInterface
{
    private const FIND_BY_ID = 'id';

    private const FIND_BY_PATH = 'path';

    private const FIND_BY_ATTRIBUTE = 'attribute';

    private mixed $dataSourceIndex;

    private string $findStrategy;

    private ?string $fallbackPath = null;

    private mixed $attributeDataObjectClassId;

    private string $attributeName;

    private string $attributeLanguage;

    private bool $saveAsVariant = false;

    public function __construct(private readonly DataObjectLoader $dataObjectLoader)
    {
    }

    public function setSettings(array $settings): void
    {
        if ($settings['dataSourceIndex'] !== 0 && $settings['dataSourceIndex'] !== '0' && empty($settings['dataSourceIndex'])) {
            throw new InvalidConfigurationException('Empty data source index.');
        }

        $this->dataSourceIndex = $settings['dataSourceIndex'];

        $this->fallbackPath = $settings['fallbackPath'] ?? null;

        if (empty($settings['findStrategy'])) {
            throw new InvalidConfigurationException('Empty find strategy.');
        }

        $this->saveAsVariant = isset($settings['asVariant']) && $settings['asVariant'] === 'on';

        $this->findStrategy = $settings['findStrategy'];

        if ($this->findStrategy == self::FIND_BY_ATTRIBUTE) {
            if (empty($settings['attributeDataObjectClassId'])) {
                throw new InvalidConfigurationException('Empty data object class for attribute loading.');
            }

            $this->attributeDataObjectClassId = $settings['attributeDataObjectClassId'];

            if (empty($settings['attributeName'])) {
                throw new InvalidConfigurationException('Empty data attribute name.');
            }

            $this->attributeName = $settings['attributeName'];
            $this->attributeLanguage = $settings['attributeLanguage'] ?? '';
        }
    }

    public function updateParent(ElementInterface $element, array $inputData): ElementInterface
    {
        $newParent = null;

        $identifier = $inputData[$this->dataSourceIndex] ?? null;

        if (isset($identifier)) {
            switch ($this->findStrategy) {
                case self::FIND_BY_ID:
                    $newParent = $this->dataObjectLoader->loadById($identifier);

                    break;
                case self::FIND_BY_PATH:
                    $newParent = $this->dataObjectLoader->loadByPath($identifier);

                    break;
                case self::FIND_BY_ATTRIBUTE:
                    $class = ClassDefinition::getById($this->attributeDataObjectClassId);
                    if (empty($class)) {
                        throw new InvalidConfigurationException("Class `{$this->attributeDataObjectClassId}` not found.");
                    }
                    $className = '\\Pimcore\\Model\\DataObject\\' . ucfirst($class->getName());
                    $newParent = $this->dataObjectLoader->loadByAttribute($className,
                        $this->attributeName,
                        $identifier,
                        $this->attributeLanguage,
                        true,
                        1
                    );

                    break;
            }
        }

        if (!($newParent instanceof DataObject) && $this->fallbackPath) {
            $newParent = DataObject::getByPath($this->fallbackPath);
        }

        if ($newParent) {
            if (
                $newParent->getType() === AbstractObject::OBJECT_TYPE_VARIANT &&
                (
                    $element->getType() !== AbstractObject::OBJECT_TYPE_VARIANT ||
                    $element::class !== $newParent::class
                )
            ) {
                throw new InvalidInputException(
                    "An element can only have a variant as a parent if it's a variant itself and of the same class."
                );
            }

            $this->setElementType($element, $newParent);

            return $element->setParent($newParent);
        }

        return $element;
    }

    /**
     * @throws InvalidInputException
     */
    private function setElementType(ElementInterface $element, DataObject | ElementInterface $newParent): void
    {
        // Check if element should be saved as a variant if not already.
        if (
            $this->saveAsVariant && $element->getType() !== AbstractObject::OBJECT_TYPE_VARIANT
        ) {
            if (
                !$element instanceof DataObject\Concrete
                || $element::class !== $newParent::class
            ) {
                throw new InvalidInputException(
                    'Changing type not possible: Only concrete objects of the same class can be saved as a variant.'
                );
            }

            if ($element->hasChildren()) {
                throw new InvalidInputException(
                    'Changing type not possible: Only objects without any children can be saved as a variant.'
                );
            }

            if (!$this->getElementClassDefinition($element)?->getAllowVariants()) {
                throw new InvalidInputException(
                    sprintf(
                        'Changing type not possible: Class `%s` is not configured to allow the creation of variants.',
                        $this->getElementClassDefinition($element)?->getName(),
                    )
                );
            }

            $element->setType(AbstractObject::OBJECT_TYPE_VARIANT);
        } elseif (
            !$this->saveAsVariant
            && $element instanceof AbstractObject
            && $element->getType() === AbstractObject::OBJECT_TYPE_VARIANT
        ) {
            if ($newParent->getType() === AbstractObject::OBJECT_TYPE_VARIANT) {
                throw new InvalidInputException(
                    'Changing type not possible: An object cannot be a child of a variant.'
                );
            }

            $element->setType(AbstractObject::OBJECT_TYPE_OBJECT);
        }
    }

    private function getElementClassDefinition(DataObject\Concrete $element): ?ClassDefinition
    {
        try {
            return $element->getClass();
        } catch (Exception) {
            return null;
        }
    }
}
