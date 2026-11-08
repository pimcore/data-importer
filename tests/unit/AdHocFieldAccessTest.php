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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FieldAccess;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Objectbrick\Data\AbstractData;

class AdHocFieldAccessTest extends Unit
{
    public function testAGroupsMandatoryMarkLeavesTheSharedDefinitionAsItIs(): void
    {
        $shared = new Input();
        $relation = new KeyGroupRelation();
        $relation->setMandatory(true);

        $inGroup = FieldAccess::inGroup($shared, $relation);

        $this->assertTrue($inGroup->getMandatory());
        $this->assertFalse($shared->getMandatory(), 'Pimcore caches the definition per key, for every group');
    }

    public function testAKeyMandatoryInItsDefinitionStaysSoInAGroupThatDoesNotMarkIt(): void
    {
        $definition = new Input();
        $definition->setMandatory(true);

        $this->assertTrue(FieldAccess::inGroup($definition, new KeyGroupRelation())->getMandatory());
    }

    public function testANeitherMandatoryKeyStaysOptional(): void
    {
        $this->assertFalse(FieldAccess::inGroup(new Input(), new KeyGroupRelation())->getMandatory());
    }

    public function testAClassFieldIsFoundOnTheClass(): void
    {
        $input = new Input();

        $this->assertSame($input, FieldAccess::definition($this->classWith(['name' => $input]), 'name'));
    }

    public function testALocalizedFieldIsFoundInTheLocalizedFields(): void
    {
        $input = new Input();
        $localized = $this->createMock(Localizedfields::class);
        $localized->method('getFieldDefinition')->with('title')->willReturn($input);

        $this->assertSame($input, FieldAccess::definition($this->classWith(['localizedfields' => $localized]), 'title'));
    }

    public function testAFieldNoneHoldsHasNoDefinition(): void
    {
        $this->assertNull(FieldAccess::definition($this->classWith([]), 'missing'));
    }

    public function testABrickFieldNeedsABrickContainerThatAllowsTheBrick(): void
    {
        $bricks = $this->createMock(Objectbricks::class);
        $bricks->method('getAllowedTypes')->willReturn(['allowed']);
        $class = $this->classWith(['bricks' => $bricks, 'name' => new Input()]);

        $this->assertNull(FieldAccess::definition($class, 'bricks.other.attribute'));
        $this->assertNull(FieldAccess::definition($class, 'name.allowed.attribute'));
    }

    public function testAFieldIsReadInItsLanguage(): void
    {
        $object = $this->createMock(Concrete::class);
        $object->expects($this->once())->method('get')->with('title', 'de')->willReturn('Stuhl');

        $this->assertSame('Stuhl', FieldAccess::read($object, 'title', 'de'));
    }

    public function testABrickFieldIsReadFromItsBrickInItsLanguage(): void
    {
        $brick = $this->createMock(AbstractData::class);
        $brick->expects($this->once())->method('get')->with('colour', 'en')->willReturn('red');

        $this->assertSame('red', FieldAccess::read($this->objectWithBrick($brick), 'bricks.paint.colour', 'en'));
    }

    public function testABrickTheObjectDoesNotHoldReadsAsNothing(): void
    {
        $this->assertNull(FieldAccess::read($this->objectWithBrick(null), 'bricks.paint.colour', null));
    }

    public function testAStoreKeyIsReadInTheDefaultLanguageWithoutFallbackOrInheritance(): void
    {
        $store = $this->createMock(Classificationstore::class);
        $store->expects($this->once())
            ->method('getLocalizedKeyValue')
            ->with(3, 7, 'default', true, true)
            ->willReturn('1 kg');
        $object = $this->createMock(Concrete::class);
        $object->method('get')->with('attributes')->willReturn($store);

        $this->assertSame('1 kg', FieldAccess::read($object, 'attributes', null, new StoreKey(3, 7)));
    }

    public function testAStoreKeyOfAnObjectWithoutTheStoreReadsAsNothing(): void
    {
        $this->assertNull(FieldAccess::read($this->createMock(Concrete::class), 'attributes', 'en', new StoreKey(3, 7)));
    }

    public function testUnpublishedElementsAreReadAndTheSettingIsRestored(): void
    {
        $before = DataObject::getHideUnpublished();
        DataObject::setHideUnpublished(true);
        $seen = null;
        $object = $this->createMock(Concrete::class);
        $object->method('get')->willReturnCallback(static function () use (&$seen): string {
            $seen = DataObject::getHideUnpublished();

            return 'value';
        });

        try {
            FieldAccess::read($object, 'name', null);

            $this->assertFalse($seen);
            $this->assertTrue(DataObject::getHideUnpublished());
        } finally {
            DataObject::setHideUnpublished($before);
        }
    }

    /**
     * @param array<string, ClassDefinition\Data> $fields
     */
    private function classWith(array $fields): ClassDefinition
    {
        $class = new ClassDefinition();
        $class->setFieldDefinitions($fields);

        return $class;
    }

    private function objectWithBrick(?AbstractData $brick): Concrete
    {
        $container = new class($brick) {
            public function __construct(private readonly ?AbstractData $brick)
            {
            }

            public function get(string $name): ?AbstractData
            {
                return $name === 'paint' ? $this->brick : null;
            }
        };
        $object = $this->createMock(Concrete::class);
        $object->method('get')->with('bricks')->willReturn($container);

        return $object;
    }
}
