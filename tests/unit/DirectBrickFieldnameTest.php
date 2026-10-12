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
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Direct;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\InheritableBrick;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;

/**
 * @see https://github.com/pimcore/data-importer/issues/398
 */
class DirectBrickFieldnameTest extends Unit
{
    private const BRICK_CLASS = 'Pimcore\\Model\\DataObject\\Objectbrick\\Data\\InheritableBrick';

    private bool $inheritedValues;

    protected function _before(): void
    {
        if (!class_exists(self::BRICK_CLASS, false)) {
            class_alias(InheritableBrick::class, self::BRICK_CLASS);
        }

        $this->inheritedValues = DataObject::getGetInheritedValues();
        DataObject::setGetInheritedValues(true);
    }

    protected function _after(): void
    {
        DataObject::setGetInheritedValues($this->inheritedValues);
    }

    public function testNewBrickGetsContainerFieldnameWhenOverwriteChecksAreActive(): void
    {
        $parent = $this->createObject(null);
        $element = $this->createObject($parent);

        $direct = new Direct();
        $direct->setSettings([
            'fieldName' => 'myBricks.inheritableBrick.title',
            'writeIfSourceIsEmpty' => false,
            'writeIfTargetIsNotEmpty' => false,
        ]);

        $direct->assignData($element, 'imported');

        $brick = $element->getMyBricks()->getInheritableBrick();
        static::assertInstanceOf(InheritableBrick::class, $brick);
        static::assertSame('myBricks', $brick->getFieldname());
        static::assertSame('imported', $brick->getTitle());
    }

    private function createObject(?Concrete $parent): Concrete
    {
        $classDefinition = new ClassDefinition();
        $classDefinition->setAllowInherit(true);

        $object = new class() extends Concrete {
            public ?Concrete $inheritanceParent = null;

            private object $myBricks;

            public function __construct()
            {
                $this->myBricks = new class() {
                    private ?InheritableBrick $inheritableBrick = null;

                    public function getInheritableBrick(): ?InheritableBrick
                    {
                        return $this->inheritableBrick;
                    }

                    public function setInheritableBrick(?InheritableBrick $inheritableBrick): static
                    {
                        $this->inheritableBrick = $inheritableBrick;

                        return $this;
                    }
                };
            }

            public function getMyBricks(): object
            {
                return $this->myBricks;
            }

            public function getNextParentForInheritance(): ?Concrete
            {
                return $this->inheritanceParent;
            }
        };
        $object->setClass($classDefinition);
        $object->inheritanceParent = $parent;

        return $object;
    }
}
