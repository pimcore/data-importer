<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type;


use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Objectbrick\Definition;

class TransformationDataTypeService
{
    CONST DEFAULT_TYPE = 'default';
    CONST DEFAULT_ARRAY = 'array';
    CONST NUMERIC = 'numeric';
    CONST BOOLEAN = 'boolean';
    CONST QUANTITY_VALUE = 'quantityValue';
    CONST INPUT_QUANTITY_VALUE = 'inputQuantityValue';
    CONST DATE = 'date';
    CONST ASSET = 'asset';
    CONST ASSET_ARRAY = 'assetArray';
    CONST GALLERY = 'gallery';
    CONST IMAGE_ADVANCED = 'imageAdvanced';
    CONST DATA_OBJECT = 'dataObject';
    CONST DATA_OBJECT_ARRAY = 'dataObjectArray';

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
        ]
    ];

    /**
     * @param string $pimcoreDataType
     * @param string $transformationTargetType
     */
    public function appendTypeMapping(string $pimcoreDataType, string $transformationTargetType): void {
        $this->transformationDataTypesMapping[$transformationTargetType][] = $pimcoreDataType;
    }


    protected function addTypesToAttributesArray(ClassDefinition\Data $fieldDefinition, string $targetType, array &$attributes, bool $localized = false, string $keyPrefix = null) {

        if(in_array($fieldDefinition->getFieldtype(), ($this->transformationDataTypesMapping[$targetType] ?? []))) {
            $key = $fieldDefinition->getName();
            if($keyPrefix) {
                $key = $keyPrefix . '.' . $key;
            }
            $attributes[$key] = [
                'key' => $key,
                'title' => $fieldDefinition->getTitle() . ' [' . $key . ']',
                'localized' => $localized
            ];
        }

        if($fieldDefinition instanceof ClassDefinition\Data\Localizedfields) {
            foreach($fieldDefinition->getFieldDefinitions() as $localizedDefinition) {
                $this->addTypesToAttributesArray($localizedDefinition, $targetType, $attributes, true, $keyPrefix);
            }
        }

        if($fieldDefinition instanceof ClassDefinition\Data\Objectbricks) {
            foreach($fieldDefinition->getAllowedTypes() as $brickType) {
                $brick = Definition::getByKey($brickType);

                foreach($brick->getFieldDefinitions() as $brickFieldDefinition) {
                    $keyPrefix = $fieldDefinition->getName() . '.' . $brickType;
                    $this->addTypesToAttributesArray($brickFieldDefinition, $targetType, $attributes, false, $keyPrefix);
                }

            }
        }

    }

    /**
     * @param string $classId
     * @param string|array $transformationTargetType
     * @param bool $includeSystemRead
     * @param bool $includeSystemWrite
     * @return array
     * @throws \Exception
     */
    public function getPimcoreDataTypes(string $classId, $transformationTargetType, bool $includeSystemRead, bool $includeSystemWrite): array {

        $class = ClassDefinition::getById($classId);

        $attributes = [];

        if(!is_array($transformationTargetType)) {
            $transformationTargetType = [$transformationTargetType];
        }

        foreach($transformationTargetType as $targetType) {
            foreach($class->getFieldDefinitions() as $definition) {

                $this->addTypesToAttributesArray($definition, $targetType, $attributes);
            }
        }

        if(in_array(self::DEFAULT_TYPE, $transformationTargetType)) {
            if($includeSystemRead) {
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
            if($includeSystemWrite) {
                $attributes['key'] = [
                    'key' => 'key',
                    'title' => 'SYSTEM Key',
                    'localized' => false
                ];
            }
        }

        return array_values($attributes);
    }

    /**
     * @param string $classId
     * @return array
     * @throws \Exception
     */
    public function getClassificationStoreAttributes(string $classId): array {
        $class = ClassDefinition::getById($classId);

        $attributes = [];
        foreach($class->getFieldDefinitions() as $definition) {
            if($definition instanceof ClassDefinition\Data\Classificationstore) {
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
     * @return array|string[]
     */
    public function getPimcoreTypesByTransformationTargetType(string $transformationTargetType): array {
        return $this->transformationDataTypesMapping[$transformationTargetType] ?? [];
    }


}
