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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Branch\ObjectChanges;
use Pimcore\Contracts\ChangeControl\DataObjectChange;
use Pimcore\Contracts\ChangeControl\ElementRef;
use Pimcore\Contracts\ChangeControl\FieldValue;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Objectbrick;
use Pimcore\Model\Document;
use Pimcore\Tool;

class AdHocObjectChangesTest extends Unit
{
    private function definition(): ClassDefinition
    {
        $title = new Data\Input();
        $title->setName('title');
        $localized = new Data\Localizedfields();
        $localized->setName('localizedfields');
        $localized->setChildren([$title]);
        // setAllowedTypes() drops a type without a stored brick definition, and the test has none
        $bricks = $this->createStub(Data\Objectbricks::class);
        $bricks->method('getName')->willReturn('bricks');
        $bricks->method('getAllowedTypes')->willReturn(['Size']);

        $definition = new ClassDefinition();
        $definition->setName('Product');
        $definition->addFieldDefinition('name', new Data\Input());
        $definition->addFieldDefinition('price', new Data\Numeric());
        $definition->addFieldDefinition('categories', new Data\ManyToManyRelation());
        $definition->addFieldDefinition('image', new Data\ManyToOneRelation());
        $definition->addFieldDefinition('localizedfields', $localized);
        $definition->addFieldDefinition('bricks', $bricks);
        $definition->addFieldDefinition('variants', new Data\Fieldcollections());
        $definition->addFieldDefinition('store', new Data\Classificationstore());

        return $definition;
    }

    /**
     * @param array<string, mixed> $values by field
     * @param array<string, array<string, mixed>> $localized by language and field
     */
    private function object(array $values, array $localized = [], ?ClassDefinition $definition = null): Concrete
    {
        $object = $this->createStub(Concrete::class);
        $object->method('getClass')->willReturn($definition ?? $this->definition());
        $object->method('get')->willReturnCallback(
            static fn (string $name, ?string $language = null): mixed => $language === null
                ? $values[$name] ?? null
                : $localized[$language][$name] ?? null
        );
        $object->method('getId')->willReturn(42);
        $object->method('getClassName')->willReturn('Product');
        $object->method('getParentId')->willReturn(3);
        $object->method('getKey')->willReturn('SKU-1');

        return $object;
    }

    /**
     * @return list<array<int, mixed>> per value: where it goes, then the value
     */
    private function values(DataObjectChange $change): array
    {
        return array_map(
            static fn (FieldValue $value): array => array_values(array_filter([
                $value->kind->value,
                $value->field,
                $value->language,
                $value->brick,
                $value->brickField,
                $value->group,
                $value->key,
            ], static fn (?string $part): bool => $part !== null)) + [7 => $value->value],
            $change->values()
        );
    }

    private function element(string $class, int $id): mixed
    {
        $element = new $class();
        $element->setId($id);

        return $element;
    }

    public function testAnExistingObjectRecordsOnlyWhatTheRowChanged(): void
    {
        $change = (new ObjectChanges())->changeOf(
            $this->object(['name' => 'Desk', 'price' => 10]),
            $this->object(['name' => 'Chair', 'price' => '10']),
            null
        );

        $this->assertSame(42, $change->id);
        $this->assertNull($change->create);
        $this->assertSame([['field', 'name', 7 => 'Desk']], $this->values($change));
    }

    public function testAnEmptiedCellEmptiesTheFieldOnTheBranch(): void
    {
        $category = $this->element(DataObject\Folder::class, 7);
        $change = (new ObjectChanges())->changeOf(
            $this->object(['name' => null, 'categories' => [], 'image' => null]),
            $this->object(['name' => 'Chair', 'categories' => [$category], 'image' => $category]),
            null
        );

        $this->assertSame(
            [['field', 'name', 7 => null], ['field', 'categories', 7 => []], ['field', 'image', 7 => null]],
            $this->values($change),
            'null removes a value, an empty list every target'
        );
    }

    public function testARelationNamesItsTargetsByTheirIds(): void
    {
        $change = (new ObjectChanges())->changeOf(
            $this->object([
                'categories' => [$this->element(DataObject\Folder::class, 7), $this->element(Asset\Image::class, 5)],
                'image' => $this->element(Document\Page::class, 3),
            ]),
            $this->object([]),
            null
        );

        $this->assertEquals(
            [
                ['field', 'categories', 7 => [ElementRef::object(7), ElementRef::asset(5)]],
                ['field', 'image', 7 => ElementRef::document(3)],
            ],
            $this->values($change)
        );
    }

    public function testANewObjectIsCreatedWithItsClassParentAndKeyUnderItsToken(): void
    {
        $change = (new ObjectChanges())->changeOf(
            $this->object(['name' => 'Chair']),
            $this->object([]),
            'row-4'
        );

        $this->assertNull($change->id);
        $this->assertSame(
            ['row-4', 'Product', 3, 'SKU-1'],
            [$change->create?->token, $change->create?->className, $change->create?->parent, $change->create?->key]
        );
        $this->assertSame([['field', 'name', 7 => 'Chair']], $this->values($change));
    }

    public function testALocalizedValueIsRecordedInItsLanguageOnly(): void
    {
        $language = Tool::getValidLanguages()[0];

        $change = (new ObjectChanges())->changeOf(
            $this->object([], [$language => ['title' => 'Chair']]),
            $this->object([], [$language => ['title' => 'Stool']]),
            null
        );

        $this->assertSame([['localized', 'title', $language, 7 => 'Chair']], $this->values($change));
    }

    public function testABrickValueIsRecordedInItsBrickAndAnEmptiedOneAsNull(): void
    {
        $size = new Objectbrick\Definition();
        $size->setKey('Size');
        $size->addFieldDefinition('width', new Data\Numeric());
        $size->addFieldDefinition('height', new Data\Numeric());

        $change = (new ObjectChanges())->changeOf(
            $this->object(['bricks' => $this->bricks($size, ['width' => 40, 'height' => null])]),
            $this->object(['bricks' => $this->bricks($size, ['width' => 30, 'height' => 90])]),
            null
        );

        $this->assertSame(
            [['brick', 'bricks', 'Size', 'width', 7 => 40], ['brick', 'bricks', 'Size', 'height', 7 => null]],
            $this->values($change)
        );
    }

    public function testABrickTheRowDeletesOrNeverHadIsNotRecorded(): void
    {
        $size = new Objectbrick\Definition();
        $size->setKey('Size');
        $size->addFieldDefinition('width', new Data\Numeric());

        $deleted = $this->bricks($size, ['width' => 40], true);
        $change = (new ObjectChanges())->changeOf(
            $this->object(['bricks' => $deleted]),
            $this->object(['bricks' => $this->bricks($size, ['width' => 30])]),
            null
        );

        $this->assertSame([], $this->values($change));
    }

    public function testAFieldCollectionIsLeftToTheObject(): void
    {
        $change = (new ObjectChanges())->changeOf(
            $this->object(['variants' => new DataObject\Fieldcollection()]),
            $this->object([]),
            null
        );

        $this->assertSame([], $this->values($change), 'a branch has no place for it');
    }

    public function testAValueThatCannotBeComparedIsRecorded(): void
    {
        $failing = new class() extends Data\Input implements Data\EqualComparisonInterface {
            public function isEqual(mixed $oldValue, mixed $newValue): bool
            {
                throw new \Error('cannot compare');
            }
        };
        $definition = new ClassDefinition();
        $definition->addFieldDefinition('name', $failing);

        $change = (new ObjectChanges())->changeOf(
            $this->object(['name' => 'Chair'], [], $definition),
            $this->object(['name' => 'Chair'], [], $definition),
            null
        );

        $this->assertSame([['field', 'name', 7 => 'Chair']], $this->values($change), 'the merge leaves it out if it is the same');
    }

    public function testAStoreKeyOfAGroupThatDoesNotExistIsNotRecorded(): void
    {
        $store = $this->createStub(Classificationstore::class);
        $store->method('getItems')->willReturn([999999 => [999998 => ['default' => 'Red']]]);

        $change = (new ObjectChanges())->changeOf(
            $this->object(['store' => $store]),
            $this->object([]),
            null
        );

        $this->assertSame([], $this->values($change));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function bricks(Objectbrick\Definition $definition, array $values, bool $deleted = false): Objectbrick
    {
        $brick = $this->createStub(Objectbrick\Data\AbstractData::class);
        $brick->method('getDefinition')->willReturn($definition);
        $brick->method('getDoDelete')->willReturn($deleted);
        $brick->method('get')->willReturnCallback(static fn (string $name): mixed => $values[$name] ?? null);

        $bricks = $this->createStub(Objectbrick::class);
        $bricks->method('get')->willReturnCallback(
            static fn (string $type): mixed => $type === $definition->getKey() ? $brick : null
        );

        return $bricks;
    }
}
