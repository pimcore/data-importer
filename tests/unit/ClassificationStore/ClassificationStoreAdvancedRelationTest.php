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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ClassificationStore;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Classificationstore;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\AdvancedManyToManyObjectRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\AdvancedManyToManyRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue as MultiValueDefinition;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineResolver;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\ElementMetadata;
use Pimcore\Model\DataObject\Data\ObjectMetadata;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * An advanced relation classification store key holds its relations as metadata objects, one per
 * related element. A pipeline hands the data target plain elements, exactly as it does for a plain
 * relation key, and the target is what has to wrap them - otherwise the key is offered in the
 * mapping editor and the related elements are dropped without a word, because
 * `AdvancedManyToManyObjectRelation::normalize()` skips everything that is no metadata object.
 */
class ClassificationStoreAdvancedRelationTest extends Unit
{
    private const STORE = 'diAdvRelStore';

    private const GROUP = 'diAdvRelGroup';

    private const CLASS_NAME = 'DiCsAdvRelClass';

    private const KEY_ADVANCED_OBJECTS = 'diAdvRelObjects';

    private const KEY_ADVANCED_ELEMENTS = 'diAdvRelElements';

    private const KEY_PLAIN_OBJECTS = 'diPlainRelObjects';

    /**
     * The same three key types once more behind a `multiValue` wrapper, which the key picker offers
     * on its delegate's type: such a key's own type column reads `multiValue` whatever it wraps.
     */
    private const KEY_WRAPPED_OBJECTS = 'diAdvRelWrappedObjects';

    private const KEY_WRAPPED_ELEMENTS = 'diAdvRelWrappedElements';

    private const KEY_WRAPPED_PLAIN_OBJECTS = 'diPlainRelWrappedObjects';

    private const TYPE_MULTI_VALUE = 'multiValue';

    private const TYPE_ADVANCED_OBJECTS = 'advancedManyToManyObjectRelation';

    private const TYPE_ADVANCED_ELEMENTS = 'advancedManyToManyRelation';

    private const TYPE_PLAIN_OBJECTS = 'manyToManyObjectRelation';

    private const ENGINE_DOCUMENT = 'document';

    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    private int $groupId;

    /**
     * @var array<string, int> key name => key id
     */
    private array $keyIds = [];

    private Concrete $related;

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE, self::ENGINE_DOCUMENT);
        $this->flipEngine($store, self::ENGINE_DOCUMENT);

        $group = $fixture->group($store->getId(), self::GROUP);
        $this->groupId = $group->getId();

        // each definition is configured to accept the related object, so that a save which still
        // fails fails on the metadata shape and not on an allowed-class rule
        $definitions = [
            self::KEY_ADVANCED_OBJECTS => [self::TYPE_ADVANCED_OBJECTS, $this->relationDefinition(
                self::TYPE_ADVANCED_OBJECTS
            )],
            self::KEY_ADVANCED_ELEMENTS => [self::TYPE_ADVANCED_ELEMENTS, $this->relationDefinition(
                self::TYPE_ADVANCED_ELEMENTS
            )],
            self::KEY_PLAIN_OBJECTS => [self::TYPE_PLAIN_OBJECTS, $this->relationDefinition(
                self::TYPE_PLAIN_OBJECTS
            )],
            self::KEY_WRAPPED_OBJECTS => [self::TYPE_MULTI_VALUE, $this->wrapped(
                self::KEY_WRAPPED_OBJECTS,
                self::TYPE_ADVANCED_OBJECTS
            )],
            self::KEY_WRAPPED_ELEMENTS => [self::TYPE_MULTI_VALUE, $this->wrapped(
                self::KEY_WRAPPED_ELEMENTS,
                self::TYPE_ADVANCED_ELEMENTS
            )],
            self::KEY_WRAPPED_PLAIN_OBJECTS => [self::TYPE_MULTI_VALUE, $this->wrapped(
                self::KEY_WRAPPED_PLAIN_OBJECTS,
                self::TYPE_PLAIN_OBJECTS
            )],
        ];

        $sorter = 0;
        foreach ($definitions as $name => [$type, $definition]) {
            $definition->setName($name);
            $key = $fixture->key($store->getId(), $name, $type, $definition);
            $fixture->relate($group->getId(), $key->getId(), ++$sorter);
            $this->keyIds[$name] = $key->getId();
        }

        $fixture->classDefinition(self::CLASS_NAME, $store->getId());
        $this->related = $fixture->object(self::CLASS_NAME, uniqid('cs-advrel-target-', false));
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    public function testAnAdvancedObjectRelationKeySurvivesTheRoundTrip(): void
    {
        $value = $this->roundTrip(self::KEY_ADVANCED_OBJECTS, [$this->related]);

        static::assertIsArray($value);
        static::assertCount(1, $value, 'the related object reached the store');
        static::assertInstanceOf(ObjectMetadata::class, $value[0]);
        static::assertSame($this->related->getId(), $value[0]->getObject()?->getId());
    }

    public function testAnAdvancedElementRelationKeySurvivesTheRoundTrip(): void
    {
        $value = $this->roundTrip(self::KEY_ADVANCED_ELEMENTS, [$this->related]);

        static::assertIsArray($value);
        static::assertCount(1, $value, 'the related element reached the store');
        static::assertInstanceOf(ElementMetadata::class, $value[0]);
        static::assertSame($this->related->getId(), $value[0]->getElement()?->getId());
    }

    /**
     * The control: a plain relation key is handed the same plain elements and must keep taking them
     * unwrapped, so the wrapping cannot have been applied to every relation key alike.
     */
    public function testAPlainObjectRelationKeyIsStillWrittenUnwrapped(): void
    {
        $value = $this->roundTrip(self::KEY_PLAIN_OBJECTS, [$this->related]);

        static::assertIsArray($value);
        static::assertCount(1, $value);
        static::assertInstanceOf(Concrete::class, $value[0], 'no metadata wrapper here');
        static::assertSame($this->related->getId(), $value[0]->getId());
    }

    public function testAMultiValueKeyOverAnAdvancedObjectRelationIsWrappedToo(): void
    {
        $value = $this->roundTrip(self::KEY_WRAPPED_OBJECTS, [$this->related]);

        static::assertIsArray($value);
        static::assertCount(1, $value, 'the related object reached the store through the wrapper');
        static::assertInstanceOf(ObjectMetadata::class, $value[0]);
        static::assertSame($this->related->getId(), $value[0]->getObject()?->getId());
    }

    public function testAMultiValueKeyOverAnAdvancedElementRelationIsWrappedToo(): void
    {
        $value = $this->roundTrip(self::KEY_WRAPPED_ELEMENTS, [$this->related]);

        static::assertIsArray($value);
        static::assertCount(1, $value, 'the related element reached the store through the wrapper');
        static::assertInstanceOf(ElementMetadata::class, $value[0]);
        static::assertSame($this->related->getId(), $value[0]->getElement()?->getId());
    }

    /**
     * The second control: the wrapper itself does not decide the wrapping, its delegate does, so a
     * `multiValue` key over a PLAIN relation still takes the elements as they arrive.
     */
    public function testAMultiValueKeyOverAPlainObjectRelationIsStillWrittenUnwrapped(): void
    {
        $value = $this->roundTrip(self::KEY_WRAPPED_PLAIN_OBJECTS, [$this->related]);

        static::assertIsArray($value);
        static::assertCount(1, $value);
        static::assertInstanceOf(Concrete::class, $value[0], 'no metadata wrapper here');
        static::assertSame($this->related->getId(), $value[0]->getId());
    }

    public function testTheBatchTargetWrapsAnAdvancedRelationValueToo(): void
    {
        $element = (new ClassificationStoreFixture())->newObject(
            self::CLASS_NAME,
            uniqid('cs-advrel-batch-', false)
        );

        $target = new ClassificationstoreBatch(
            $this->createMock(ApplicationLogger::class),
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings(['fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED]);
        $target->assignData($element, [
            $this->groupId . '-' . $this->keyIds[self::KEY_ADVANCED_OBJECTS] => [$this->related],
        ]);
        $element->save();

        $value = $this->reloadKeyValue($element, self::KEY_ADVANCED_OBJECTS);

        static::assertIsArray($value);
        static::assertCount(1, $value);
        static::assertInstanceOf(ObjectMetadata::class, $value[0]);
    }

    /**
     * One relation definition of the given type, configured to accept the related object.
     */
    private function relationDefinition(string $type): Data
    {
        if ($type === self::TYPE_ADVANCED_OBJECTS) {
            $definition = new AdvancedManyToManyObjectRelation();
            $definition->setAllowedClassId(self::CLASS_NAME);

            return $definition;
        }

        if ($type === self::TYPE_ADVANCED_ELEMENTS) {
            $definition = new AdvancedManyToManyRelation();
            $definition->setObjectsAllowed(true);

            return $definition;
        }

        return new ManyToManyObjectRelation();
    }

    /**
     * A `multiValue` key over one of the relation types, declaring no variants - the shape the key
     * picker offers whenever a key's `delegateDatatype` matches the result type of the pipeline.
     */
    private function wrapped(string $name, string $delegateType): MultiValueDefinition
    {
        $delegate = $this->relationDefinition($delegateType);
        $delegate->setName($name);

        $definition = new MultiValueDefinition();
        $definition->setDelegateDatatype($delegateType);
        $definition->setDelegate($delegate);

        return $definition;
    }

    /**
     * @param list<Concrete> $value
     *
     * @return mixed the value read back out of a reloaded object
     */
    private function roundTrip(string $keyName, array $value): mixed
    {
        // ClassificationStoreFixture::newObject() for why only the first save can be relied on here
        $element = (new ClassificationStoreFixture())->newObject(
            self::CLASS_NAME,
            uniqid('cs-advrel-', false)
        );

        $target = new Classificationstore(
            $this->createMock(ApplicationLogger::class),
            (new ClassificationStoreFixture())->localizationResolver()
        );
        $target->setSettings([
            'fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED,
            'keyId' => $this->groupId . '-' . $this->keyIds[$keyName],
        ]);
        $target->assignData($element, $value);
        $element->save();

        return $this->reloadKeyValue($element, $keyName);
    }

    private function reloadKeyValue(Concrete $element, string $keyName): mixed
    {
        $reloaded = Concrete::getById((int) $element->getId(), ['force' => true]);
        static::assertInstanceOf(Concrete::class, $reloaded);

        $getter = 'get' . ucfirst(ClassificationStoreFixture::FIELD_NON_LOCALIZED);
        /** @var ClassificationstoreModel $classificationStore */
        $classificationStore = $reloaded->$getter();

        return $classificationStore->getLocalizedKeyValue(
            $this->groupId,
            $this->keyIds[$keyName],
            'default'
        );
    }

    private function flipEngine(StoreConfig $store, string $engine): void
    {
        $store->setEngine($engine);
        $store->save();

        /** @var EngineResolver $resolver */
        $resolver = $this->tester->grabService(EngineResolver::class);
        $resolver->reset();
    }
}
