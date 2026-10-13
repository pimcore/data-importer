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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\Direct;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;

/**
 * @see https://github.com/pimcore/data-importer/issues/444
 */
class DirectDataTargetTest extends Unit
{
    private string $rootFolderName;

    protected function _before(): void
    {
        $this->rootFolderName = 'direct-data-target-test-' . uniqid();
    }

    protected function _after(): void
    {
        $rootFolder = DataObject::getByPath('/' . $this->rootFolderName);
        $rootFolder?->delete();
    }

    public function testEmptyFieldNameThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $target = new Direct();
        $target->setSettings([]);
    }

    public function testPathOnNonDataObjectThrows(): void
    {
        $target = $this->createPathTarget();

        $this->expectException(InvalidConfigurationException::class);
        $target->assignData($this->createMock(ElementInterface::class), '/some/folder');
    }

    public function testPathCreatesFolderAndSetsParent(): void
    {
        $element = new DataObject\Folder();
        $element->setParentId(1);

        $path = '/' . $this->rootFolderName . '/sub/folder';
        $this->createPathTarget()->assignData($element, $path);

        $parent = DataObject::getByPath($path);
        static::assertInstanceOf(DataObject\Folder::class, $parent);
        static::assertSame($parent->getId(), $element->getParentId());
    }

    public function testEmptyPathKeepsParent(): void
    {
        $element = new DataObject\Folder();
        $element->setParentId(1);

        $this->createPathTarget()->assignData($element, '');

        static::assertSame(1, $element->getParentId());
    }

    public function testWriteIfTargetIsNotEmptyDisabledOnlyMovesNewElements(): void
    {
        $target = $this->createPathTarget(['writeIfTargetIsNotEmpty' => false]);
        $path = '/' . $this->rootFolderName . '/target';

        // New element (the resolver has already placed it in its creation location).
        $newElement = new DataObject\Folder();
        $newElement->setParentId(1);
        $target->assignData($newElement, $path);
        static::assertSame(DataObject::getByPath($path)->getId(), $newElement->getParentId());

        // Existing element is not moved.
        $existingElement = new DataObject\Folder();
        $existingElement->setId(PHP_INT_MAX);
        $existingElement->setParentId(1);
        $target->assignData($existingElement, $path);
        static::assertSame(1, $existingElement->getParentId());
    }

    public function testParentOfExistingVariantCannotBeChanged(): void
    {
        $variant = $this->createConcrete(AbstractObject::OBJECT_TYPE_VARIANT);
        $variant->setId(PHP_INT_MAX);

        $this->expectException(InvalidInputException::class);
        $this->createPathTarget()->assignData($variant, '/' . $this->rootFolderName . '/variants');
    }

    public function testParentOfNewVariantCanBeSet(): void
    {
        $variant = $this->createConcrete(AbstractObject::OBJECT_TYPE_VARIANT);

        $path = '/' . $this->rootFolderName . '/variants';
        $this->createPathTarget()->assignData($variant, $path);

        static::assertSame(DataObject::getByPath($path)->getId(), $variant->getParentId());
    }

    private function createPathTarget(array $settings = []): Direct
    {
        $target = new Direct();
        $target->setSettings(['fieldName' => 'path'] + $settings);

        return $target;
    }

    private function createConcrete(string $type): Concrete
    {
        $object = new class() extends Concrete {
        };
        $object->setType($type);
        $object->setParentId(1);

        return $object;
    }
}
