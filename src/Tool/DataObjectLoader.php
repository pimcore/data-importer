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
use Pimcore\Model\Element\ElementInterface;

class DataObjectLoader
{
    const CLASS_FIELD_NAME = 'classFieldName';
    const BRICK_NAME = 'brickName';
    const BRICK_ATTRIBUTE_NAME = 'brickFieldName';
    const BRICK_ATTRIBUTE_SEPARATOR = '.';

    /**
     * @param string $attributeName
     *
     * @return bool
     */
    private function isObjectBrickAttribute(string $attributeName): bool
    {
        return str_contains($attributeName, self::BRICK_ATTRIBUTE_SEPARATOR);
    }

    /**
     * @param string $attributeName
     *
     * @return array
     */
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

    /**
     * @param array $objectBrickParts
     * @param bool $includeClassFieldName
     *
     * @return string
     */
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
     * @param string $className
     * @param string $attributeName
     * @param string $identifier
     * @param string $attributeLanguage
     * @param bool $includeUnpublished
     * @param int $limit
     * @param string $operator
     *
     * @return ElementInterface|null
     */
    public function loadByAttribute(string $className,
                                    string $attributeName,
                                    string $identifier,
                                    string $attributeLanguage = '',
                                    bool $includeUnpublished = false,
                                    int $limit = 0,
                                    string $operator = '='): ?ElementInterface
    {
        $element = null;

        if ($includeUnpublished) {
            $className::setHideUnpublished(false);
        }

        if ($this->isObjectBrickAttribute($attributeName) === false && $operator === '=') {
            $getter = 'getBy' . $attributeName;
            if (empty($attributeLanguage) === false) {
                $element = $className::$getter($identifier, $attributeLanguage, $limit);
            } else {
                $element = $className::$getter($identifier, $limit);
            }
        } else {
            $queryFieldName = $attributeName;
            if ($this->isObjectBrickAttribute($attributeName) === true) {
                $objectBrickParts = $this->getObjectBrickParts($attributeName);
                $queryFieldName = $this->getAttributeNameFromParts($objectBrickParts, false);
                $conditions = ['objectbricks' => [$objectBrickParts[self::BRICK_NAME]]];
            }
            $conditions['condition'] = $queryFieldName . ' ' . $operator . ' ' . Db::get()->quote($identifier);
            if ($limit > 0) {
                $conditions['limit'] = $limit;
            }
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

    /**
     * @param string $identifier
     * @param string $className
     *
     * @return ElementInterface|null
     */
    public function loadById(string $identifier,
                             string $className = '\\Pimcore\\Model\\DataObject'): ?ElementInterface
    {
        return $className::getById($identifier);
    }

    /**
     * @param string $identifier
     * @param string $className
     *
     * @return ElementInterface|null
     */
    public function loadByPath(string $identifier,
                               string $className = '\\Pimcore\\Model\\DataObject'): ?ElementInterface
    {
        return $className::getByPath($identifier);
    }
}
