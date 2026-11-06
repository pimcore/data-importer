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
use Pimcore\Model\Element\ElementInterface;

/**
 * @internal
 */
final class DataObjectLoader
{
    private const CLASS_FIELD_NAME = 'classFieldName';

    private const BRICK_NAME = 'brickName';

    private const BRICK_ATTRIBUTE_NAME = 'brickFieldName';

    private const BRICK_ATTRIBUTE_SEPARATOR = '.';

    private const SYSTEM_KEY_ATTRIBUTE = 'key';

    private const SQL_AND = ' AND ';

    // a field name, or `<field>.<brick>.<attribute>` for an object brick attribute
    private const ATTRIBUTE_NAME_PATTERN = '/^\w+(?:\.\w+\.\w+)?\z/';

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

    // $path extends a public signature, a parameter object would break callers
    public function loadByAttribute(// NOSONAR php:S107
        string $className,
        string $attributeName,
        string $identifier,
        string $attributeLanguage = '',
        bool $includeUnpublished = false,
        int $limit = 0,
        string $operator = '=',
        string $path = ''
    ): ?ElementInterface {
        // the name goes into the listing condition unquoted for partial matches and object brick attributes
        if (preg_match(self::ATTRIBUTE_NAME_PATTERN, $attributeName) !== 1) {
            throw new InvalidConfigurationException(sprintf('Invalid attribute name "%s".', $attributeName));
        }

        $element = null;
        $path = $this->normalizePath($path);
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
                $objectTypes,
                $path
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

            $conditions['condition'] = $queryFieldName . ' ' . $operator . ' ' . $identifierQuoted
                . $this->getPathCondition($path);
            $element = $this->loadFirstFromList($className, $conditions, $limit, $objectTypes);
        }

        if ($element instanceof ElementInterface) {
            return $element;
        }

        return null;
    }

    /**
     * The ids of the objects whose attribute has the value, published or not, at or below the given normalized path,
     * or only directly in it (`/` is the tree root then); unlike loadByAttribute() it tells one match from several.
     *
     * @return list<int>
     */
    public function findIdsByAttribute(
        string $className,
        string $attributeName,
        string $identifier,
        string $attributeLanguage,
        string $path,
        int $limit,
        bool $directlyInPath = false
    ): array {
        // the name goes into the listing condition unquoted for object brick attributes
        if (preg_match(self::ATTRIBUTE_NAME_PATTERN, $attributeName) !== 1) {
            throw new InvalidConfigurationException(sprintf('Invalid attribute name "%s".', $attributeName));
        }

        $db = Db::get();
        $options = [
            'objectTypes' => [DataObject::OBJECT_TYPE_VARIANT, DataObject::OBJECT_TYPE_OBJECT],
            'unpublished' => true,
            'limit' => $limit,
        ];

        $container = '';
        if ($this->isObjectBrickAttribute($attributeName)) {
            $objectBrickParts = $this->getObjectBrickParts($attributeName);
            $options['objectbricks'] = [$objectBrickParts[self::BRICK_NAME]];
            $field = $this->getAttributeNameFromParts($objectBrickParts, false);
            // a brick can sit in several fields of a class, and its table has one row per field
            $container = $objectBrickParts[self::BRICK_NAME] . '.fieldname = '
                . $db->quote($objectBrickParts[self::CLASS_FIELD_NAME]) . self::SQL_AND;
        } else {
            $field = $db->quoteIdentifier($attributeName);
        }

        if ($attributeLanguage !== '') {
            $options['locale'] = $attributeLanguage;
        }

        $pathCondition = $directlyInPath
            ? self::SQL_AND . $db->quoteIdentifier('path') . ' = ' . $db->quote($path)
            : $this->getPathCondition($path);
        $options['condition'] = $container . $field . ' = ' . $db->quote($identifier) . $pathCondition;

        return $className::getList($options)->loadIdList();
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
        array $objectTypes,
        string $path
    ): ?ElementInterface {
        if ($attributeName === self::SYSTEM_KEY_ATTRIBUTE) {
            return $this->loadBySystemKey($className, $identifier, $limit, $objectTypes, $path);
        }

        $getter = 'getBy' . $attributeName;

        if ($path !== '' && empty($attributeLanguage) && method_exists($className, $getter)) {
            // a hand-written static getter takes no list options, so the path could not reach the query
            $db = Db::get();

            return $this->loadFirstFromList($className, [
                'condition' => $db->quoteIdentifier($attributeName) . ' = ' . $db->quote($identifier)
                    . $this->getPathCondition($path),
            ], $limit, $objectTypes);
        }

        if ($path !== '') {
            // the generated static getters accept list options instead of a limit, whose condition is appended to
            // the lookup condition
            $listOptions = ['limit' => $limit > 0 ? $limit : 1, 'condition' => $this->getPathCondition($path)];
            if (empty($attributeLanguage) === false) {
                return $className::$getter($identifier, $attributeLanguage, $listOptions, 0, $objectTypes);
            }

            return $className::$getter($identifier, $listOptions, 0, $objectTypes);
        }

        if (empty($attributeLanguage) === false) {
            return $className::$getter($identifier, $attributeLanguage, $limit, 0, $objectTypes);
        }

        if (method_exists($className, $getter)) {
            return $className::$getter($identifier);
        }

        return $className::$getter($identifier, $limit, 0, $objectTypes);
    }

    /**
     * `key` is a system column, not a field definition, so `$className::getByKey()` ends up in
     * AbstractObject::__callStatic() through a non-forwarding parent:: call and queries objects
     * of all classes. Query the class listing directly so the configured class is respected.
     */
    private function loadBySystemKey(
        string $className,
        string $identifier,
        int $limit,
        array $objectTypes,
        string $path
    ): ?ElementInterface {
        $db = Db::get();

        return $this->loadFirstFromList($className, [
            'condition' => $db->quoteIdentifier(self::SYSTEM_KEY_ATTRIBUTE) . ' = ' . $db->quote($identifier)
                . $this->getPathCondition($path),
        ], $limit, $objectTypes);
    }

    private function loadFirstFromList(
        string $className,
        array $conditions,
        int $limit,
        array $objectTypes
    ): ?ElementInterface {
        if ($limit > 0) {
            $conditions['limit'] = $limit;
        }
        $conditions['objectTypes'] = $objectTypes;

        $dataObjects = $className::getList($conditions)->load();

        return $dataObjects[0] ?? null;
    }

    /**
     * Normalizes the configured tree path to `/folder/sub/` (leading and trailing slash), `''` for no restriction.
     */
    public function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '';
        }

        return '/' . trim($path, '/') . '/';
    }

    /**
     * SQL condition (with leading ` AND `) limiting a lookup to objects at or below the given normalized path.
     * Objects store the path of their parent folder in `path`, so a prefix match covers the folder itself and
     * all sub folders. LIKE wildcards in the path are escaped and the value is bound as a quoted literal.
     */
    public function getPathCondition(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return self::SQL_AND . $this->getPathLikeCondition($path);
    }

    /**
     * The path condition without a leading `AND`, `$path` must be normalized and not empty.
     */
    public function getPathLikeCondition(string $path): string
    {
        $db = Db::get();

        return $db->quoteIdentifier('path') . ' LIKE ' . $db->quote(addcslashes($path, '\\%_') . '%');
    }
}
