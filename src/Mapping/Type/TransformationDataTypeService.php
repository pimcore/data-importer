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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Type;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Objectbrick\Definition;

/**
 * @internal
 */
final class TransformationDataTypeService
{
    public const DEFAULT_TYPE = 'default';

    public const DEFAULT_ARRAY = 'array';

    public const NUMERIC = 'numeric';

    public const BOOLEAN = 'boolean';

    public const QUANTITY_VALUE = 'quantityValue';

    public const QUANTITY_VALUE_ARRAY = 'quantityValueArray';

    public const INPUT_QUANTITY_VALUE = 'inputQuantityValue';

    public const INPUT_QUANTITY_VALUE_ARRAY = 'inputQuantityValueArray';

    public const DATE = 'date';

    public const DATE_ARRAY = 'dateArray';

    public const ASSET = 'asset';

    public const ASSET_ARRAY = 'assetArray';

    public const GALLERY = 'gallery';

    public const IMAGE_ADVANCED = 'imageAdvanced';

    public const DATA_OBJECT = 'dataObject';

    public const DATA_OBJECT_ARRAY = 'dataObjectArray';

    public const ADVANCED_DATA_OBJECT_ARRAY = 'advancedDataObjectArray';

    public const ADVANCED_ASSET_ARRAY = 'advancedAssetArray';

    public const GEOPOINT_VALUE = 'geoPoint';

    public const GEOBOUNDS_VALUE = 'geoBounds';

    public const GEOPOLYGON_VALUE = 'geoPolygon';

    public const GEOPOLYLINE_VALUE = 'geoPolyline';

    public const RGBA_COLOR = 'rgbaColor';

    public const COUNTRY_ARRAY = 'countryArray';

    public const CALCULATED = 'calculated';

    private array $transformationDataTypesMapping = [
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
            'gender',
        ],
        self::NUMERIC => [
            'numeric',
            'slider'
        ],
        self::DEFAULT_ARRAY => [
            'multiselect',
            'countrymultiselect',
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
        ],
        self::GEOPOINT_VALUE => [
            'geopoint'
        ],
        self::GEOBOUNDS_VALUE => [
            'geobounds'
        ],
        self::GEOPOLYGON_VALUE => [
            'geopolygon'
        ],
        self::GEOPOLYLINE_VALUE => [
            'geopolyline'
        ],
        self::RGBA_COLOR => [
            'rgbaColor'
        ],
        self::COUNTRY_ARRAY => [
            'countrymultiselect'
        ],
        self::CALCULATED =>[
            'calculatedValue'
        ],
    ];

    public function appendTypeMapping(string $pimcoreDataType, string $transformationTargetType): void
    {
        $this->transformationDataTypesMapping[$transformationTargetType][] = $pimcoreDataType;
    }

    private function addTypesToAttributesArray(ClassDefinition\Data $fieldDefinition, string $targetType, array &$attributes, bool $localized = false, ?string $keyPrefix = null)
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
                // Allow reading from calculated fields
                foreach ($class->getFieldDefinitions() as $definition) {
                    $this->addTypesToAttributesArray($definition, self::CALCULATED, $attributes);
                }
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
                $attributes['path'] = [
                    'key' => 'path',
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
