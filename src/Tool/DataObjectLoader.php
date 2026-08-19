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

namespace Pimcore\Bundle\DataImporterBundle\Tool;

use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Db;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\Element\ElementInterface;
use function sprintf;

/**
 * @internal
 */
final class DataObjectLoader
{
    private const CLASS_FIELD_NAME = 'classFieldName';

    private const BRICK_NAME = 'brickName';

    private const BRICK_ATTRIBUTE_NAME = 'brickFieldName';

    private const BRICK_ATTRIBUTE_SEPARATOR = '.';

    private function isObjectBrickAttribute(string $attributeName): bool
    {
        return str_contains($attributeName, self::BRICK_ATTRIBUTE_SEPARATOR);
    }

    private function getObjectBrickParts(string $attributeName): array
    {
        $parts = explode(self::BRICK_ATTRIBUTE_SEPARATOR, $attributeName);
        if (count($parts) === 3) {
            return [self::CLASS_FIELD_NAME => $parts[0],
                self::BRICK_NAME => $parts[1],
                self::BRICK_ATTRIBUTE_NAME => $parts[2]];
        }

        return [];
    }

    private function getAttributeNameFromParts(array $objectBrickParts,
        bool $includeClassFieldName): string
    {
        $brickName = $objectBrickParts[self::BRICK_NAME] ?? '';
        $brickAttributeName = $objectBrickParts[self::BRICK_ATTRIBUTE_NAME] ?? '';
        $classFieldName = $objectBrickParts[self::CLASS_FIELD_NAME] ?? '';

        $fullAttributeName = $brickName . self::BRICK_ATTRIBUTE_SEPARATOR . $brickAttributeName;
        if ($includeClassFieldName === true) {
            $fullAttributeName = $classFieldName . self::BRICK_ATTRIBUTE_SEPARATOR . $fullAttributeName;
        }

        return $fullAttributeName;
    }

    /**
     * Asserts that an attribute can actually be used to look an object up.
     *
     * This mirrors what loadByAttribute() does rather than what a field can hold: a dotted
     * object brick path is resolved through a listing condition, everything else goes through
     * Pimcore's getBy<Field>() static getter, which refuses a field whose type is not
     * filterable. Gating on the transformation data type instead rejected perfectly loadable
     * fields (dates, relations, checkboxes, multiselects) while accepting `password`, whose
     * getter throws.
     *
     * @throws InvalidConfigurationException
     */
    public function assertAttributeLoadable(string $classId, string $attributeName): void
    {
        if ($attributeName === '') {
            throw new InvalidConfigurationException('The attributeName attribute is required.');
        }

        $classDefinition = ClassDefinition::getById($classId);
        if (!$classDefinition instanceof ClassDefinition) {
            throw new InvalidConfigurationException(
                sprintf('Class `%s` not found. Make sure to use an existing data object class ID.', $classId)
            );
        }

        if ($this->isObjectBrickAttribute($attributeName)) {
            if ($this->getObjectBrickParts($attributeName) === []) {
                throw new InvalidConfigurationException(sprintf(
                    'Object brick attribute `%s` must be given as `classField.brickName.brickField`.',
                    $attributeName
                ));
            }

            return;
        }

        $fieldDefinition = $this->resolveFieldDefinition($classDefinition, $attributeName);
        if (!$fieldDefinition instanceof Data) {
            throw new InvalidConfigurationException(sprintf(
                'Attribute `%s` does not exist in class `%s`.',
                $attributeName,
                $classDefinition->getName()
            ));
        }

        if (!$fieldDefinition->isFilterable()) {
            throw new InvalidConfigurationException(sprintf(
                'Attribute `%s` cannot be used to load an object: field type `%s` is not filterable.',
                $attributeName,
                $fieldDefinition->getFieldType()
            ));
        }
    }

    private function resolveFieldDefinition(ClassDefinition $classDefinition, string $attributeName): ?Data
    {
        $fieldDefinition = $classDefinition->getFieldDefinition($attributeName);
        if ($fieldDefinition instanceof Data) {
            return $fieldDefinition;
        }

        // getBy<Field>() falls back to the localized fields container, so a localized child is
        // just as loadable as a top level field.
        $localizedFields = $classDefinition->getFieldDefinition('localizedfields');
        if ($localizedFields instanceof Localizedfields) {
            $localizedDefinition = $localizedFields->getFieldDefinition($attributeName);
            if ($localizedDefinition instanceof Data) {
                return $localizedDefinition;
            }
        }

        return null;
    }

    public function loadByAttribute(string $className,
        string $attributeName,
        string $identifier,
        string $attributeLanguage = '',
        bool $includeUnpublished = false,
        int $limit = 0,
        string $operator = '='): ?ElementInterface
    {
        $element = null;
        $objectTypes = [DataObject::OBJECT_TYPE_VARIANT, DataObject::OBJECT_TYPE_OBJECT];

        if ($includeUnpublished) {
            $className::setHideUnpublished(false);
        }

        if ($this->isObjectBrickAttribute($attributeName) === false && $operator === '=' && $identifier !== '') {
            $element = $this->getDataObject(
                $attributeName,
                $className,
                $identifier,
                $attributeLanguage,
                $limit,
                $objectTypes
            );
        } else {
            $queryFieldName = $attributeName;
            if ($this->isObjectBrickAttribute($attributeName) === true) {
                $objectBrickParts = $this->getObjectBrickParts($attributeName);
                $queryFieldName = $this->getAttributeNameFromParts($objectBrickParts, false);
                $conditions = ['objectbricks' => [$objectBrickParts[self::BRICK_NAME]]];
            }

            // Pimcore stores empty string as NULL so we need to use IS NULL for lookup
            if ($operator === '=' && $identifier === '') {
                $identifierQuoted = 'NULL';
                $operator = 'IS';
            } else {
                $identifierQuoted = Db::get()->quote($identifier);
            }

            $conditions['condition'] = $queryFieldName . ' ' . $operator . ' ' . $identifierQuoted;
            if ($limit > 0) {
                $conditions['limit'] = $limit;
            }
            $conditions['objectTypes'] = $objectTypes;
            $list = $className::getList($conditions);
            $dataObjects = $list->load();
            if (empty($dataObjects) === false) {
                $element = $dataObjects[0];
            }
        }

        if ($element instanceof ElementInterface) {
            return $element;
        }

        return null;
    }

    public function loadById(string $identifier,
        string $className = '\\Pimcore\\Model\\DataObject'): ?ElementInterface
    {
        return $className::getById((int) $identifier);
    }

    public function loadByPath(string $identifier,
        string $className = '\\Pimcore\\Model\\DataObject'): ?ElementInterface
    {
        return $className::getByPath($identifier);
    }

    private function getDataObject(
        string $attributeName,
        string $className,
        string $identifier,
        string $attributeLanguage,
        int $limit,
        array $objectTypes
    ): ?ElementInterface {
        $getter = 'getBy' . $attributeName;

        if ($attributeName === 'id') {
            return $className::getById((int) $identifier);
        }

        if (empty($attributeLanguage) === false) {
            return $className::$getter($identifier, $attributeLanguage, $limit, 0, $objectTypes);
        }

        if (method_exists($className, $getter)) {
            return $className::$getter($identifier);
        }

        return $className::$getter($identifier, $limit, 0, $objectTypes);
    }
}
