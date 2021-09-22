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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\AbstractOperator;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;

class LoadDataObject extends AbstractOperator
{
    const LOAD_STRATEGY_ID = 'id';
    const LOAD_STRATEGY_PATH = 'path';
    const LOAD_STRATEGY_ATTRIBUTE = 'attribute';

    /**
     * @var string
     */
    protected $loadStrategy;

    /**
     * @var string
     */
    protected $attributeLanguage;

    /**
     * @var string
     */
    protected $attributeName;

    /**
     * @var string
     */
    protected $attributeDataObjectClassId;

    /**
     * @var bool
     */
    protected $partialMatch;

    public function setSettings(array $settings): void
    {
        $this->loadStrategy = $settings['loadStrategy'] ?? self::LOAD_STRATEGY_ID;
        $this->attributeLanguage = $settings['attributeLanguage'];
        $this->attributeName = $settings['attributeName'];
        $this->attributeDataObjectClassId = $settings['attributeDataObjectClassId'];
        $this->partialMatch = $settings['partialMatch'] ?? false;
    }

    public function process($inputData, bool $dryRun = false)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        $objects = [];

        foreach ($inputData as $data) {
            $object = null;
            if ($this->loadStrategy === self::LOAD_STRATEGY_PATH) {
                $object = DataObject::getByPath(trim($data));
            } elseif ($this->loadStrategy === self::LOAD_STRATEGY_ID) {
                $object = DataObject::getById(trim($data));
            } elseif ($this->loadStrategy === self::LOAD_STRATEGY_ATTRIBUTE) {
                if ($this->attributeName) {
                    $getter = 'getBy' . $this->attributeName;
                    $class = ClassDefinition::getById($this->attributeDataObjectClassId);
                    if (empty($class)) {
                        throw new InvalidConfigurationException("Class `{$this->attributeDataObjectClassId}` not found.");
                    }
                    $className = '\\Pimcore\\Model\\DataObject\\' . ucfirst($class->getName());

                    if ($this->partialMatch) {
                        $listClassName = $className . '\\Listing';
                        $listing = new $listClassName();
                        $listing->setCondition($this->attributeName . ' LIKE ' . $listing->quote($data));
                        $listing->setLimit(1);
                        if ($this->attributeLanguage) {
                            $listing->setLocale($this->attributeLanguage);
                        }
                        $object = $listing->load()[0] ?? null;
                    } else {
                        if ($this->attributeLanguage) {
                            $object = $className::$getter($data, $this->attributeLanguage, 1);
                        } else {
                            $object = $className::$getter($data, 1);
                        }
                    }
                }
            } else {
                throw new InvalidConfigurationException("Unknown load strategy '{ $this->loadStrategy }'");
            }

            if ($object instanceof DataObject) {
                $objects[] = $object;
            } elseif (!$dryRun && !empty($data)) {
                $this->applicationLogger->warning("Could not load data object from `$data` ", [
                    'component' => PimcoreDataImporterBundle::LOGGER_COMPONENT_PREFIX . $this->configName,
                ]);
            }
        }

        if ($returnScalar) {
            if (!empty($objects)) {
                return reset($objects);
            }

            return null;
        } else {
            return $objects;
        }
    }

    /**
     * @param string $inputType
     * @param int|null $index
     *
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function evaluateReturnType(string $inputType, int $index = null): string
    {
        if ($inputType === TransformationDataTypeService::DEFAULT_TYPE) {
            return TransformationDataTypeService::DATA_OBJECT;
        } elseif ($inputType === TransformationDataTypeService::DEFAULT_ARRAY) {
            return TransformationDataTypeService::DATA_OBJECT_ARRAY;
        } else {
            throw new InvalidConfigurationException(sprintf("Unsupported input type '%s' for load data object operator at transformation position %s", $inputType, $index));
        }
    }

    public function generateResultPreview($inputData)
    {
        $returnScalar = false;
        if (!is_array($inputData)) {
            $returnScalar = true;
            $inputData = [$inputData];
        }

        foreach ($inputData as &$data) {
            if ($data instanceof DataObject) {
                $data = 'DataObject: ' . $data->getFullPath() . ' (ID: ' . $data->getId() . ')';
            }
        }

        if ($returnScalar) {
            return reset($inputData);
        } else {
            return $inputData;
        }
    }
}
