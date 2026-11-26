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

use Pimcore\Bundle\DataImporterBundle\Mapping\Type\ClassificationStoreEngineSupport;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore as ClassificationstoreDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Panel;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineResolver;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\KeyTypeSupport;
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;
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

    /**
     * Built here because this fixture is a plain class, not a Codeception test, and has no `$tester` to grab the
     * core service with.
     */
    public function localizationResolver(): LocalizationResolver
    {
        return new LocalizationResolver(Db::get());
    }

    /**
     * The engine question the batch data target asks, taken out of the container: both core
     * services behind it are declared `public: true` there, and neither can be built by hand -
     * `KeyTypeSupport` needs the object data implementation loader and the marshaller service, and
     * `EngineResolver` needs the tagged engines. Reached through the kernel rather than
     * `Pimcore::getContainer()`, which is deprecated.
     */
    public function engineSupport(): ClassificationStoreEngineSupport
    {
        $container = \Pimcore::getKernel()?->getContainer();
        if ($container === null) {
            throw new \UnexpectedValueException('The Pimcore container is not booted.');
        }

        /** @var KeyTypeSupport $keyTypeSupport */
        $keyTypeSupport = $container->get(KeyTypeSupport::class);
        /** @var EngineResolver $engineResolver */
        $engineResolver = $container->get(EngineResolver::class);

        return new ClassificationStoreEngineSupport($keyTypeSupport, $engineResolver);
    }

    /**
     * A store saved rather than created writes no engine column, which means the default engine.
     *
     * @param string|null $engine the engine the store is created on, null for the default one
     */
    public function store(string $name, ?string $engine = null): StoreConfig
    {
        $store = StoreConfig::getByName($name);
        if ($store instanceof StoreConfig) {
            return $store;
        }

        $store = new StoreConfig();
        $store->setName($name);
        if ($engine !== null) {
            $store->setEngine($engine);
        }

        $store->save();

        return $store;
    }

    /**
     * Moves a saved store onto another engine, leaving the declarations of its keys as they stand.
     */
    public function flipEngine(StoreConfig $store, string $engine): void
    {
        $store->setEngine($engine);
        $store->save();
    }

    public function group(int $storeId, string $name, ?string $identifier = null): GroupConfig
    {
        $group = GroupConfig::getByName($name, $storeId);
        if ($group instanceof GroupConfig) {
            return $group;
        }

        $group = new GroupConfig();
        $group->setStoreId($storeId);
        $group->setName($name);
        if ($identifier !== null) {
            $group->setIdentifier($identifier);
        }

        $group->save();

        return $group;
    }

    /**
     * @param Data|null $definition the field definition the key renders with; the key's own type is
     *                              used to build it back
     * @param bool|null $localized the key's own flag, null for a key that states none
     */
    public function key(
        int $storeId,
        string $name,
        string $type,
        ?Data $definition = null,
        ?string $identifier = null,
        ?bool $localized = null
    ): KeyConfig
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
        $keyConfig->setLocalized($localized);
        if ($identifier !== null) {
            $keyConfig->setIdentifier($identifier);
        }

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

    /**
     * A saved object, for a test that only reads the in-memory store and never writes to the
     * database. A test that saves must use {@see self::newObject()} instead.
     */
    public function object(string $className, string $key): Concrete
    {
        $object = $this->newObject($className, $key);
        $object->save();

        return $object;
    }

    /**
     * An object that has never been saved, so that a test which assigns data and then saves spends
     * exactly one save on it — and that save is an insert.
     *
     * **This is not a style preference, it is what keeps the save path reachable in CI.** A second
     * save is an update, and `DataObjectIndexUpdateSubscriber::updateDataObject()` answers
     * `POST_UPDATE` — and only `POST_UPDATE` — by calling `IndexElementIndexService::updateSiblings()`,
     * which reads the element back out of the search index before it compares indices
     * (`updateSiblings()` → `getIndexedIndex()` → `DataObjectSearchService::byId()`). That is a live
     * query against a search cluster, and neither this repository's codeception workflow nor
     * `tests/bin/docker-compose.yml` runs one, so the second save raises
     * `SearchFailedException: No alive nodes found in your cluster`. `POST_ADD` only queues the
     * element and never searches, so an insert is unaffected.
     *
     * Nothing is given up by writing before the first save: `Classificationstore\Dao::save()` deletes
     * every row for the `(object, fieldname)` pair and re-inserts, so an insert and an update take the
     * identical write path through the marshaller and the two value columns.
     */
    public function newObject(string $className, string $key): Concrete
    {
        $fqcn = '\\Pimcore\\Model\\DataObject\\' . $className;
        $parent = Service::createFolderByPath('/' . $className);

        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent($parent);
        $object->setKey($key);
        $object->setPublished(true);

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
