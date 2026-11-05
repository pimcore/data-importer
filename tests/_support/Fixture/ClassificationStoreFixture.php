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

namespace Pimcore\Bundle\DataImporterBundle\Tests\Fixture;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore as ClassificationstoreDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Panel;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;

/**
 * Classification store configuration rows and a data object class holding a localized and a
 * non-localized classification store field, as the classification store data target tests need them.
 *
 * Modelled on core's own `AbstractClassificationStoreTest::configureStore()`: the rows are created
 * idempotently and left in place, the way `DataObjectLoaderSystemKeyTest` leaves its classes.
 */
final class ClassificationStoreFixture
{
    public const FIELD_NON_LOCALIZED = 'csField';

    public const FIELD_LOCALIZED = 'csLocalizedField';

    public function store(string $name): StoreConfig
    {
        $store = StoreConfig::getByName($name);
        if ($store instanceof StoreConfig) {
            return $store;
        }

        $store = new StoreConfig();
        $store->setName($name);
        $store->save();

        return $store;
    }

    public function group(int $storeId, string $name): GroupConfig
    {
        $group = GroupConfig::getByName($name, $storeId);
        if ($group instanceof GroupConfig) {
            return $group;
        }

        $group = new GroupConfig();
        $group->setStoreId($storeId);
        $group->setName($name);
        $group->save();

        return $group;
    }

    /**
     * @param Data|null $definition the field definition the key renders with; the key's own type is
     *                              used to build it back
     */
    public function key(int $storeId, string $name, string $type, ?Data $definition = null): KeyConfig
    {
        $keyConfig = KeyConfig::getByName($name, $storeId);
        if ($keyConfig instanceof KeyConfig) {
            return $keyConfig;
        }

        $keyConfig = new KeyConfig();
        $keyConfig->setStoreId($storeId);
        $keyConfig->setName($name);
        $keyConfig->setDescription($name);
        $keyConfig->setEnabled(true);
        $keyConfig->setType($type);
        $keyConfig->setDefinition((string)json_encode($definition ?? new Input()));
        $keyConfig->save();

        return $keyConfig;
    }

    public function relate(int $groupId, int $keyId, int $sorter = 1): KeyGroupRelation
    {
        $relation = KeyGroupRelation::getByGroupAndKeyId($groupId, $keyId);
        if ($relation instanceof KeyGroupRelation) {
            return $relation;
        }

        $relation = new KeyGroupRelation();
        $relation->setGroupId($groupId);
        $relation->setKeyId($keyId);
        $relation->setSorter($sorter);
        $relation->save();

        return $relation;
    }

    /**
     * A class with one non-localized and one localized classification store field on the given store.
     */
    public function classDefinition(string $name, int $storeId): ClassDefinition
    {
        $existing = ClassDefinition::getByName($name);
        if ($existing instanceof ClassDefinition) {
            return $existing;
        }

        $panel = new Panel();
        $panel->setName('pimcore_root');
        $panel->addChild($this->classificationStoreField(self::FIELD_NON_LOCALIZED, $storeId, false));
        $panel->addChild($this->classificationStoreField(self::FIELD_LOCALIZED, $storeId, true));

        $definition = new ClassDefinition();
        $definition->setName($name);
        $definition->setLayoutDefinitions($panel);
        $definition->save();

        return $definition;
    }

    public function object(string $className, string $key): Concrete
    {
        $fqcn = '\\Pimcore\\Model\\DataObject\\' . $className;
        $parent = Service::createFolderByPath('/' . $className);

        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent($parent);
        $object->setKey($key);
        $object->setPublished(true);
        $object->save();

        return $object;
    }

    private function classificationStoreField(
        string $name,
        int $storeId,
        bool $localized
    ): ClassificationstoreDefinition {
        $field = new ClassificationstoreDefinition();
        $field->setName($name);
        $field->setTitle($name);
        $field->setStoreId($storeId);
        $field->setLocalized($localized);

        return $field;
    }
}
