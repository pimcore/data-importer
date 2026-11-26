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
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreBatch;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Geopolygon as GeopolygonDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToOneRelation as ManyToOneRelationDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Multiselect as MultiselectDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\RgbaColor as RgbaColorDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Table as TableDefinition;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\GeoCoordinates;
use Pimcore\Model\DataObject\Data\RgbaColor;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * A mixed `array` batch entry over a store that holds structured values: a key whose value is a
 * model object is built by its own type out of the shape that type's `normalize()` emits, and a key
 * whose value IS an array keeps it.
 *
 * The three cases are the three ways the raw array used to be mishandled - warned about and skipped,
 * stored as the raw array with no warning at all, and raising a `TypeError` that
 * `ImportProcessingService` cannot catch - with `multiselect` and `table` as the control that the
 * types which already worked are untouched.
 */
class ClassificationstoreBatchTypedValueTest extends Unit
{
    private const STORE = 'diTypedStore';

    private const ENGINE_DOCUMENT = 'document';

    private const GROUP = 'diTypedGroup';

    private const CLASS_NAME = 'DiCsTypedClass';

    private const KEY_COLOR = 'diTypedColor';

    private const KEY_POLYGON = 'diTypedPolygon';

    private const KEY_RELATION = 'diTypedRelation';

    private const KEY_MULTISELECT = 'diTypedMultiselect';

    private const KEY_TABLE = 'diTypedTable';

    /**
     * The shape `RgbaColor::normalize()` emits, which is therefore the shape its `denormalize()`
     * reads.
     */
    private const COLOR = ['r' => 12, 'g' => 34, 'b' => 56, 'a' => 255];

    private const POLYGON = [
        ['latitude' => 1.5, 'longitude' => 2.5],
        ['latitude' => 3.5, 'longitude' => 4.5],
    ];

    private const MULTISELECT = ['one', 'two'];

    private const TABLE = [['a', 'b']];

    private int $groupId;

    /**
     * @var array<string, int>
     */
    private array $keyIds = [];

    private Concrete $related;

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE, self::ENGINE_DOCUMENT);
        // a store saved rather than created writes no engine column, so it is flipped explicitly
        $fixture->flipEngine($store, self::ENGINE_DOCUMENT);
        $group = $fixture->group($store->getId(), self::GROUP);

        $sorter = 1;
        foreach ($this->keyDefinitions() as $name => [$type, $definition]) {
            $key = $fixture->key($store->getId(), $name, $type, $definition);
            $fixture->relate($group->getId(), $key->getId(), $sorter++);
            $this->keyIds[$name] = $key->getId();
        }

        $fixture->classDefinition(self::CLASS_NAME, $store->getId());
        $this->groupId = $group->getId();
        $this->related = $fixture->object(self::CLASS_NAME, uniqid('cs-typed-target-', false));
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    /**
     * The first fault class: a type whose `normalize()` keeps only its own model object normalised
     * the raw array to null, so the key was warned about and skipped and the value was lost.
     */
    public function testAColorKeyBuildsItsModelObjectRatherThanBeingSkipped(): void
    {
        $element = $this->write([$this->token(self::KEY_COLOR) => self::COLOR]);

        $value = $this->keyValue($element, self::KEY_COLOR);
        static::assertInstanceOf(RgbaColor::class, $value, 'the raw array was not built into a colour');
        static::assertSame(12, $value->getR());
        static::assertSame(34, $value->getG());
        static::assertSame(56, $value->getB());
        static::assertSame(255, $value->getA());
    }

    /**
     * The second fault class: `Geopolygon::normalize()` answers non-null for a raw array of raw
     * points, so nothing flagged it and the key stored the arrays themselves.
     */
    public function testAPolygonKeyHoldsCoordinatesRatherThanRawArrays(): void
    {
        $element = $this->write([$this->token(self::KEY_POLYGON) => self::POLYGON]);

        $value = $this->keyValue($element, self::KEY_POLYGON);
        static::assertIsArray($value);
        static::assertCount(2, $value);
        foreach ($value as $index => $point) {
            static::assertInstanceOf(GeoCoordinates::class, $point, 'point ' . $index . ' is a raw array');
            static::assertSame(self::POLYGON[$index]['latitude'], $point->getLatitude());
            static::assertSame(self::POLYGON[$index]['longitude'], $point->getLongitude());
        }
    }

    /**
     * The third fault class: `ManyToOneRelation::normalize()` hands the raw array to
     * `Element\Service::getElementType()`, which raises a `TypeError` - an `\Error`, which the
     * `catch (\Exception)` of `ImportProcessingService` does not catch, so one such cell ended the
     * whole import run.
     */
    public function testARelationKeyResolvesItsElementRatherThanRaising(): void
    {
        $element = $this->write([
            $this->token(self::KEY_RELATION) => ['type' => 'object', 'id' => $this->related->getId()],
        ]);

        $value = $this->keyValue($element, self::KEY_RELATION);
        static::assertInstanceOf(Concrete::class, $value);
        static::assertSame($this->related->getId(), $value->getId());
    }

    /**
     * A cell of the wrong shape for its key, which is what a source column carries when it was never
     * meant for that key. `ManyToOneRelation::normalize()` hands it to
     * `Element\Service::getElementType()` and that raises a `TypeError` - an `\Error`, which
     * `ImportProcessingService::processQueueItem()` does not catch, so before this it ended the run.
     * It is now read as a value the entry cannot build: the key is warned about and skipped, and
     * every other key of the same entry is still written.
     */
    public function testAMalformedValueIsWarnedAboutWhileTheRestOfTheEntryIsWritten(): void
    {
        $relationToken = $this->token(self::KEY_RELATION);
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::once())->method('warning')->with(
            static::callback(static fn (string $message): bool => str_contains($message, $relationToken)
                && str_contains($message, 'manyToOneRelation')),
            static::anything()
        );

        $element = $this->write([
            $relationToken => 'not a relation descriptor',
            $this->token(self::KEY_MULTISELECT) => self::MULTISELECT,
        ], $logger);

        static::assertNull(
            $this->keyValue($element, self::KEY_RELATION),
            'the malformed relation value was written'
        );
        static::assertSame(
            self::MULTISELECT,
            $this->keyValue($element, self::KEY_MULTISELECT),
            'the rest of the entry was lost'
        );
    }

    /**
     * The control for the whole change, and the one row of it that must not move: a type whose
     * normalised form IS the array keeps the value it was given, because the built value is taken
     * only where it differs from that array.
     *
     * @dataProvider arrayValuedKeys
     *
     * @param array<int|string, mixed> $value
     */
    public function testAnArrayValuedKeyKeepsTheArrayItWasGiven(string $keyName, array $value): void
    {
        $element = $this->write([$this->token($keyName) => $value]);

        static::assertSame($value, $this->keyValue($element, $keyName), $keyName . ' was rebuilt');
    }

    /**
     * @return array<string, array{string, array<int|string, mixed>}>
     */
    public function arrayValuedKeys(): array
    {
        return [
            'multiselect' => [self::KEY_MULTISELECT, self::MULTISELECT],
            'table' => [self::KEY_TABLE, self::TABLE],
        ];
    }

    /**
     * Every key of one entry, so that the per-value dispatch is shown to act per key rather than per
     * entry: the two built values and the two kept ones reach the store side by side, and none of
     * them is warned about.
     */
    public function testOneEntryCarriesBuiltAndKeptValuesSideBySide(): void
    {
        $logger = $this->createMock(ApplicationLogger::class);
        $logger->expects(static::never())->method('warning');

        $element = $this->write([
            $this->token(self::KEY_COLOR) => self::COLOR,
            $this->token(self::KEY_POLYGON) => self::POLYGON,
            $this->token(self::KEY_MULTISELECT) => self::MULTISELECT,
            $this->token(self::KEY_TABLE) => self::TABLE,
        ], $logger);

        static::assertInstanceOf(RgbaColor::class, $this->keyValue($element, self::KEY_COLOR));
        static::assertInstanceOf(GeoCoordinates::class, $this->keyValue($element, self::KEY_POLYGON)[0]);
        static::assertSame(self::MULTISELECT, $this->keyValue($element, self::KEY_MULTISELECT));
        static::assertSame(self::TABLE, $this->keyValue($element, self::KEY_TABLE));
    }

    /**
     * @return array<string, array{string, Data|null}> key name => [key type, field definition]
     */
    private function keyDefinitions(): array
    {
        $relation = new ManyToOneRelationDefinition();
        $relation->setObjectsAllowed(true);

        return [
            self::KEY_COLOR => ['rgbaColor', $this->named(new RgbaColorDefinition(), self::KEY_COLOR)],
            self::KEY_POLYGON => ['geopolygon', $this->named(new GeopolygonDefinition(), self::KEY_POLYGON)],
            self::KEY_RELATION => ['manyToOneRelation', $this->named($relation, self::KEY_RELATION)],
            self::KEY_MULTISELECT => [
                'multiselect',
                $this->named(new MultiselectDefinition(), self::KEY_MULTISELECT),
            ],
            self::KEY_TABLE => ['table', $this->named(new TableDefinition(), self::KEY_TABLE)],
        ];
    }

    private function named(Data $definition, string $name): Data
    {
        $definition->setName($name);

        return $definition;
    }

    /**
     * One entry through the batch target, against a fresh object.
     *
     * @param array<string, mixed> $entry
     */
    private function write(array $entry, ?ApplicationLogger $logger = null): Concrete
    {
        $fixture = new ClassificationStoreFixture();
        $element = $fixture->object(self::CLASS_NAME, uniqid('cs-typed-', false));

        $target = new ClassificationstoreBatch(
            $logger ?? $this->createMock(ApplicationLogger::class),
            $fixture->localizationResolver(),
            $fixture->engineSupport()
        );
        $target->setSettings(['fieldName' => ClassificationStoreFixture::FIELD_NON_LOCALIZED]);
        $target->assignData($element, $entry);

        return $element;
    }

    private function token(string $keyName): string
    {
        return $this->groupId . '-' . $this->keyIds[$keyName];
    }

    private function keyValue(Concrete $element, string $keyName): mixed
    {
        $getter = 'get' . ucfirst(ClassificationStoreFixture::FIELD_NON_LOCALIZED);
        $classificationStore = $element->$getter();
        static::assertInstanceOf(ClassificationstoreModel::class, $classificationStore);

        return $classificationStore->getLocalizedKeyValue($this->groupId, $this->keyIds[$keyName]);
    }
}
