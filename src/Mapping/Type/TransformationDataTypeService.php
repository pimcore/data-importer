<?php


namespace Pimcore\Bundle\DataHubBatchImportBundle\Mapping\Type;


use Pimcore\Model\DataObject\ClassDefinition;

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
                if(in_array($definition->getFieldtype(), ($this->transformationDataTypesMapping[$targetType] ?? []))) {
                    $attributes[$definition->getName()] = [
                        'key' => $definition->getName(),
                        'title' => $definition->getTitle() . ' [' . $definition->getName() . ']',
                        'localized' => false
                    ];
                }
            }

            if($localizedFields = $class->getFieldDefinition('localizedfields')) {
                foreach($localizedFields->getFieldDefinitions() as $definition) {
                    if(in_array($definition->getFieldtype(), ($this->transformationDataTypesMapping[$targetType] ?? []))) {
                        $attributes[$definition->getName()] = [
                            'key' => $definition->getName(),
                            'title' => $definition->getTitle() . ' [' . $definition->getName() . ']',
                            'localized' => true
                        ];
                    }
                }
            }

        }

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

        return array_values($attributes);
    }



}
