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

namespace Pimcore\Bundle\DataHubBatchImportBundle\Resolver\Location;

use Pimcore\Bundle\DataHubBatchImportBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject;
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

    public function setSettings(array $settings): void
    {
        if (empty($settings['dataSourceIndex'])) {
            throw new InvalidConfigurationException('Empty data source index.');
        }

        $this->dataSourceIndex = $settings['dataSourceIndex'];

        $this->fallbackPath = $settings['fallbackPath'] ?? null;

        if (empty($settings['findStrategy'])) {
            throw new InvalidConfigurationException('Empty find strategy.');
        }

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

        switch ($this->findStrategy) {

            case self::FIND_BY_ID:
                $newParent = DataObject::getById($inputData[$this->dataSourceIndex] ?? null);
                break;
            case self::FIND_BY_PATH:
                $newParent = DataObject::getByPath($inputData[$this->dataSourceIndex] ?? null);
                break;
            case self::FIND_BY_ATTRIBUTE:
                $getter = 'getBy' . $this->attributeName;
                $class = ClassDefinition::getById($this->attributeDataObjectClassId);
                if (empty($class)) {
                    throw new InvalidConfigurationException("Class `{$this->attributeDataObjectClassId}` not found.");
                }
                $className = '\\Pimcore\\Model\\DataObject\\' . $class->getName();

                $identifier = $inputData[$this->dataSourceIndex] ?? null;
                if ($this->attributeLanguage) {
                    $newParent = $className::$getter($identifier, $this->attributeLanguage, 1);
                } else {
                    $newParent = $className::$getter($identifier, 1);
                }

                break;
        }

        if (!($newParent instanceof DataObject) && $this->fallbackPath) {
            $newParent = DataObject::getByPath($this->fallbackPath);
        }

        if ($newParent) {
            return $element->setParent($newParent);
        }

        return $element;
    }

    protected function loadById()
    {
    }
}
