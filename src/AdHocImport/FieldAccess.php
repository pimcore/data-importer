<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Objectbrick;

/**
 * Reads a field the Direct data target writes, named as it names it: a field of the class, a localized field with
 * its language, or `<container>.<brick>.<field>` for an object brick attribute.
 *
 * @internal
 */
final class FieldAccess
{
    private const string LOCALIZED_FIELDS = 'localizedfields';

    public static function definition(ClassDefinition $class, string $field, ?StoreKey $storeKey = null): ?Data
    {
        if ($storeKey !== null) {
            $relation = Classificationstore\KeyGroupRelation::getByGroupAndKeyId($storeKey->groupId, $storeKey->keyId);
            if ($relation === null) {
                return null;
            }
            $definition = Classificationstore\Service::getFieldDefinitionFromKeyConfig($relation);

            return $definition === null ? null : self::inGroup($definition, $relation);
        }

        $parts = explode('.', $field);
        if (count($parts) === 3) {
            [$container, $brick, $attribute] = $parts;
            $containerDefinition = $class->getFieldDefinition($container);
            if (
                !$containerDefinition instanceof Objectbricks
                || !in_array($brick, $containerDefinition->getAllowedTypes(), true)
            ) {
                return null;
            }

            return Objectbrick\Definition::getByKey($brick)?->getFieldDefinition($attribute);
        }

        $definition = $class->getFieldDefinition($field);
        if ($definition !== null) {
            return $definition;
        }
        $localizedFields = $class->getFieldDefinition(self::LOCALIZED_FIELDS);

        return $localizedFields instanceof Localizedfields ? $localizedFields->getFieldDefinition($field) : null;
    }

    /**
     * Saving an object also requires a key its group marks as mandatory. Pimcore caches one definition per key, and the
     * mark differs per group, so a copy carries it.
     */
    public static function inGroup(Data $definition, Classificationstore\KeyGroupRelation $relation): Data
    {
        if (!$relation->isMandatory()) {
            return $definition;
        }

        $definition = clone $definition;
        $definition->setMandatory(true);

        return $definition;
    }

    /**
     * The field's value as the object holds it now, unpublished related elements included.
     */
    public static function read(Concrete $object, string $field, ?string $language, ?StoreKey $storeKey = null): mixed
    {
        $hideUnpublished = DataObject::getHideUnpublished();
        DataObject::setHideUnpublished(false);

        try {
            if ($storeKey !== null) {
                $store = $object->get($field);

                // the value of the language itself, as the import writes it, without fallback or default
                return $store instanceof Classificationstore ? $store->getLocalizedKeyValue(
                    $storeKey->groupId,
                    $storeKey->keyId,
                    $language ?? 'default',
                    true,
                    true
                ) : null;
            }

            $parts = explode('.', $field);
            if (count($parts) !== 3) {
                return $object->get($field, $language);
            }

            [$container, $brick, $attribute] = $parts;
            $brickData = $object->get($container)?->get($brick);

            return $brickData instanceof Objectbrick\Data\AbstractData ? $brickData->get($attribute, $language) : null;
        } finally {
            DataObject::setHideUnpublished($hideUnpublished);
        }
    }
}
