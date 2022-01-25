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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Type;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Objectbrick\Definition;

class TransformationDataTypeService
{
    const DEFAULT_TYPE = 'default';
    const DEFAULT_ARRAY = 'array';
    const NUMERIC = 'numeric';
    const BOOLEAN = 'boolean';
    const QUANTITY_VALUE = 'quantityValue';
    const QUANTITY_VALUE_ARRAY = 'quantityValueArray';
    const INPUT_QUANTITY_VALUE = 'inputQuantityValue';
    const INPUT_QUANTITY_VALUE_ARRAY = 'inputQuantityValueArray';
    const DATE = 'date';
    const DATE_ARRAY = 'dateArray';
    const ASSET = 'asset';
    const ASSET_ARRAY = 'assetArray';
    const GALLERY = 'gallery';
    const IMAGE_ADVANCED = 'imageAdvanced';
    const DATA_OBJECT = 'dataObject';
    const DATA_OBJECT_ARRAY = 'dataObjectArray';
    const ADVANCED_DATA_OBJECT_ARRAY = 'advancedDataObjectArray';
    const ADVANCED_ASSET_ARRAY = 'advancedAssetArray';

    protected $transformationDataTypesMapping = [
        self::DEFAULT_TYPE => [
            'input',
            'textarea',
            'wysiwyg',
            'password',
            'select',
            'user',
            'country',
            'language',
            'firstname',
            'lastname',
            'email',
            'gender'
        ],
        self::NUMERIC => [
            'numeric',
            'slider'
        ],
        self::DEFAULT_ARRAY => [
            'multiselect',
            'countries',
            'languages'
        ],
        self::QUANTITY_VALUE => [
            'quantityValue'
        ],
        self::INPUT_QUANTITY_VALUE => [
            'inputQuantityValue'
        ],
        self::BOOLEAN => [
            'booleanSelect',
            'checkbox',
            'numeric',
            'input'
        ],
        self::DATE => [
            'date',
            'datetime'
        ],
        self::ASSET => [
            'image',
            'manyToOneRelation'
        ],
        self::ASSET_ARRAY => [
            'manyToManyRelation'
        ],
        self::ADVANCED_ASSET_ARRAY => [
            'manyToManyRelation',
            'advancedManyToManyRelation'
        ],
        self::GALLERY => [
            'imageGallery'
        ],
        self::IMAGE_ADVANCED => [
            'hotspotimage'
        ],
        self::DATA_OBJECT => [
            'manyToOneRelation'
        ],
        self::DATA_OBJECT_ARRAY => [
            'manyToManyRelation',
            'manyToManyObjectRelation'
        ],
        self::ADVANCED_DATA_OBJECT_ARRAY => [
            'manyToManyRelation',
            'advancedManyToManyRelation',
            'manyToManyObjectRelation',
            'advancedManyToManyObjectRelation'
        ]
    ];

    /**
     * @param string $pimcoreDataType
     * @param string $transformationTargetType
     */
    public function appendTypeMapping(string $pimcoreDataType, string $transformationTargetType): void
    {
        $this->transformationDataTypesMapping[$transformationTargetType][] = $pimcoreDataType;
    }

    protected function addTypesToAttributesArray(ClassDefinition\Data $fieldDefinition, string $targetType, array &$attributes, bool $localized = false, string $keyPrefix = null)
    {
        if (in_array($fieldDefinition->getFieldtype(), ($this->transformationDataTypesMapping[$targetType] ?? []))) {
            $key = $fieldDefinition->getName();
            if ($keyPrefix) {
                $key = $keyPrefix . '.' . $key;
            }
            $attributes[$key] = [
                'key' => $key,
                'title' => $fieldDefinition->getTitle() . ' [' . $key . ']',
                'localized' => $localized
            ];
        }

        if ($fieldDefinition instanceof ClassDefinition\Data\Localizedfields) {
            foreach ($fieldDefinition->getFieldDefinitions() as $localizedDefinition) {
                $this->addTypesToAttributesArray($localizedDefinition, $targetType, $attributes, true, $keyPrefix);
            }
        }

        if ($fieldDefinition instanceof ClassDefinition\Data\Objectbricks) {
            foreach ($fieldDefinition->getAllowedTypes() as $brickType) {
                $brick = Definition::getByKey($brickType);

                foreach ($brick->getFieldDefinitions() as $brickFieldDefinition) {
                    $keyPrefix = $fieldDefinition->getName() . '.' . $brickType;
                    $this->addTypesToAttributesArray($brickFieldDefinition, $targetType, $attributes, false, $keyPrefix);
                }
            }
        }
    }

    /**
     * @param string $classId
     * @param array|string $transformationTargetType
     * @param bool $includeSystemRead
     * @param bool $includeSystemWrite
     * @param bool $includeAdvancedRelations
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getPimcoreDataTypes(string $classId, $transformationTargetType, bool $includeSystemRead, bool $includeSystemWrite, bool $includeAdvancedRelations): array
    {
        $class = ClassDefinition::getById($classId);

        $attributes = [];

        if (!is_array($transformationTargetType)) {
            $transformationTargetType = [$transformationTargetType];
        }

        //replace for advanced relations
        if ($includeAdvancedRelations) {
            $transformationTargetType = array_map(function ($item) {
                switch ($item) {
                    case self::ASSET_ARRAY:
                        return self::ADVANCED_ASSET_ARRAY;
                    case self::DATA_OBJECT_ARRAY:
                        return self::ADVANCED_DATA_OBJECT_ARRAY;
                    default:
                        return $item;
                }
            }, $transformationTargetType);
        }

        foreach ($transformationTargetType as $targetType) {
            foreach ($class->getFieldDefinitions() as $definition) {
                $this->addTypesToAttributesArray($definition, $targetType, $attributes);
            }
        }

        if (in_array(self::DEFAULT_TYPE, $transformationTargetType)) {
            if ($includeSystemRead) {
                $attributes['id'] = [
                    'key' => 'id',
                    'title' => 'SYSTEM ID',
                    'localized' => false
                ];
                $attributes['key'] = [
                    'key' => 'key',
                    'title' => 'SYSTEM Key',
                    'localized' => false
                ];
                $attributes['fullpath'] = [
                    'key' => 'fullpath',
                    'title' => 'SYSTEM Fullpath',
                    'localized' => false
                ];
            }
            if ($includeSystemWrite) {
                $attributes['key'] = [
                    'key' => 'key',
                    'title' => 'SYSTEM Key',
                    'localized' => false
                ];
            }
        }

        if ($class->getAllowVariants()) {
            $attributes['type'] = [
                'key' => 'type',
                'title' => 'SYSTEM Object Type ("variant"|"object")',
                'localized' => false
            ];
        }

        return array_values($attributes);
    }

    /**
     * @param string $classId
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getClassificationStoreAttributes(string $classId): array
    {
        $class = ClassDefinition::getById($classId);

        $attributes = [];
        foreach ($class->getFieldDefinitions() as $definition) {
            if ($definition instanceof ClassDefinition\Data\Classificationstore) {
                $attributes[$definition->getName()] = [
                    'key' => $definition->getName(),
                    'title' => $definition->getTitle() . ' [' . $definition->getName() . ']',
                    'localized' => $definition->isLocalized()
                ];
            }
        }

        return array_values($attributes);
    }

    /**
     * @param string $transformationTargetType
     *
     * @return array|string[]
     */
    public function getPimcoreTypesByTransformationTargetType(string $transformationTargetType): array
    {
        return $this->transformationDataTypesMapping[$transformationTargetType] ?? [];
    }
}
