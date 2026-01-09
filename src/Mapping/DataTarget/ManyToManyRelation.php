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

namespace Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Data\ElementMetadata;
use Pimcore\Model\DataObject\Data\ObjectMetadata;
use Pimcore\Model\Element\Service;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ManyToManyRelation extends Direct
{
    const OVERWRITE_MODE_MERGE = 'merge';

    const OVERWRITE_MODE_REPLACE = 'replace';

    /**
     * @var bool
     */
    protected $overwriteMode;

    /**
     * @param array $settings
     *
     * @throws InvalidConfigurationException
     */
    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);
        $this->overwriteMode = $settings['overwriteMode'] ?? self::OVERWRITE_MODE_REPLACE;
    }

    /**
     * @param DataObject\Concrete|DataObject\Objectbrick\Data\AbstractData $valueContainer
     * @param string $fieldName
     * @param mixed $data
     *
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    protected function doAssignData($valueContainer, $fieldName, $data)
    {
        $fieldDefinition = $this->getFieldDefinition($valueContainer, $fieldName);

        switch ($fieldDefinition->getFieldtype()) {
            case 'manyToManyRelation':
            case 'manyToManyObjectRelation':
            case 'advancedManyToManyRelation':
            case 'advancedManyToManyObjectRelation':

                $setter = 'set' . ucfirst($fieldName);
                $getter = 'get' . ucfirst($fieldName);
                $valueContainer->$setter(
                    $this->getMergedDataArray($valueContainer, $getter, $fieldDefinition->getFieldtype(), $data),
                    $this->language
                );

                break;

            default:
                throw new InvalidConfigurationException('Invalid field type for attribute ' . $fieldName .
                    '. Only supports advanced relation types, ' . $fieldDefinition->getFieldtype() . ' given.');
        }
    }

    /**
     * @param object $valueContainer
     * @param string $getter
     * @param string $fieldType
     * @param mixed $data
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getMergedDataArray($valueContainer, string $getter, string $fieldType, $data): array
    {
        if (null === $data) {
            return [];
        }

        $currentData = [];
        if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
            $hideUnpublished = DataObject::getHideUnpublished();
            DataObject::setHideUnpublished(false);
            $currentData = $valueContainer->$getter($this->language);
            DataObject::setHideUnpublished($hideUnpublished);
        }

        $newData = [];
        switch ($fieldType) {
            case 'manyToManyObjectRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($currentData as $dataObject) {
                        $newData[$dataObject->getId()] = $dataObject;
                    }

                    foreach ($data as $dataObject) {
                        if (!isset($newData[$dataObject->getId()])) {
                            $newData[$dataObject->getId()] = $dataObject;
                        }
                    }
                } else {
                    return is_array($data) ? $data : [$data];
                }

                break;

            case 'advancedManyToManyObjectRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($currentData as $metaDataObject) {
                        $newData[$metaDataObject->getObject()->getId()] = $metaDataObject;
                    }
                }
                foreach ($data as $dataObject) {
                    if ($this->overwriteMode == self::OVERWRITE_MODE_REPLACE || !isset($newData[$dataObject->getId()])) {
                        $metaDataObject = new ObjectMetadata($this->fieldName, [], $dataObject);
                        $newData[$metaDataObject->getObject()->getId()] = $metaDataObject;
                    }
                }

                break;

            case 'manyToManyRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($currentData as $element) {
                        $newData[Service::getElementType($element) . '_' . $element->getId()] = $element;
                    }
                    foreach ($data as $element) {
                        if (!isset($newData[Service::getElementType($element) . '_' . $element->getId()])) {
                            $newData[Service::getElementType($element) . '_' . $element->getId()] = $element;
                        }
                    }
                } else {
                    return is_array($data) ? $data : [$data];
                }

                break;

            case 'advancedManyToManyRelation':
                if ($this->overwriteMode == self::OVERWRITE_MODE_MERGE) {
                    foreach ($currentData as $metaDataElement) {
                        $newData[Service::getElementType($metaDataElement->getElement()) . '_' .
                        $metaDataElement->getElement()->getId()] = $metaDataElement;
                    }
                }
                foreach ($data as $element) {
                    if ($this->overwriteMode == self::OVERWRITE_MODE_REPLACE ||
                        !isset($newData[Service::getElementType($element) . '_' . $element->getId()])) {
                        $metaDataElement = new ElementMetadata($this->fieldName, [], $element);
                        $newData[Service::getElementType($metaDataElement->getElement()) . '_' . $element->getId()] =
                            $metaDataElement;
                    }
                }

                break;

        }

        return array_values($newData);
    }

    public function getSchemaDescription(): string
    {
        return 'Many-to-many relation field mapping target with merge/replace modes';
    }

    public function getConfigTreeBuilder(): ?TreeBuilder
    {
        $parentTreeBuilder = parent::getConfigTreeBuilder();
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $parentTreeBuilder->getRootNode();

        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->enumNode('overwriteMode')
                    ->info('How to handle existing relations: merge with existing or replace completely')
                    ->values([self::OVERWRITE_MODE_MERGE, self::OVERWRITE_MODE_REPLACE])
                    ->defaultValue(self::OVERWRITE_MODE_REPLACE)
                ->end()
            ->end();

        return $parentTreeBuilder;
    }
}
