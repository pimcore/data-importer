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

namespace Pimcore\Bundle\DataImporterBundle\Tool;

use Pimcore\Db;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;

class DataObjectLoader
{
    const CLASS_FIELD_NAME = 'classFieldName';
    const BRICK_NAME = 'brickName';
    const BRICK_ATTRIBUTE_NAME = 'brickFieldName';
    const BRICK_ATTRIBUTE_SEPARATOR = '.';

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
            $getter = 'getBy' . $attributeName;
            if (empty($attributeLanguage) === false) {
                $element = $className::$getter($identifier, $attributeLanguage, $limit, 0, $objectTypes);
            } else {
                if (method_exists($className, $getter)) {
                    $element = $className::$getter($identifier);
                }

                if (!$element) {
                    $element = $className::$getter($identifier, $limit, 0, $objectTypes);
                }
            }
        } else {
            $queryFieldName = $attributeName;
            if ($this->isObjectBrickAttribute($attributeName) === true) {
                $objectBrickParts = $this->getObjectBrickParts($attributeName);
                $queryFieldName = $this->getAttributeNameFromParts($objectBrickParts, false);
                $conditions = ['objectbricks' => [$objectBrickParts[self::BRICK_NAME]]];
            }

            // Pimcore stores empty string as NULL so we need to use IS NULL for lookup
            if ($operator === '=' && $identifier === '') {
                $identifierQuoted  = 'NULL';
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
        return $className::getById($identifier);
    }

    public function loadByPath(string $identifier,
                               string $className = '\\Pimcore\\Model\\DataObject'): ?ElementInterface
    {
        return $className::getByPath($identifier);
    }
}
