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

namespace Pimcore\Bundle\DataImporterBundle\Resolver\Location;

use Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Element\ElementInterface;

class FindParentStrategy implements LocationStrategyInterface
{
    const FIND_BY_ID = 'id';
    const FIND_BY_PATH = 'path';
    const FIND_BY_ATTRIBUTE = 'attribute';

    /**
     * @var mixed
     */
    protected $dataSourceIndex;

    /**
     * @var string
     */
    protected $findStrategy;

    /**
     * @var string
     */
    protected $fallbackPath;

    /**
     * @var mixed
     */
    protected $attributeDataObjectClassId;

    /**
     * @var string
     */
    protected $attributeName;

    /**
     * @var string
     */
    protected $attributeLanguage;

    protected bool $saveAsVariant = false;

    public function __construct(protected DataObjectLoader $dataObjectLoader)
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
            $this->attributeLanguage = $settings['attributeLanguage'] ?? null;
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
                                                                        false,
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
                    "An element can only have a variant as a parent if it's of the same class."
                );
            }

            try {
                // Check if element should be saved as a variant.
                if (
                    $this->saveAsVariant
                    && $element instanceof DataObject\Concrete
                    && $element::class === $newParent::class
                    && !$element->hasChildren()
                    && $element->getClass()->getAllowVariants()
                ) {
                    $element->setType(AbstractObject::OBJECT_TYPE_VARIANT);
                }

                return $element->setParent($newParent);
            } catch (Exception) {
                // Exception might be thrown by $element->getClass().
            }
        }

        return $element;
    }

    protected function loadById()
    {
    }
}
