<?php declare(strict_types=1);

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
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;

/**
 * @see https://github.com/pimcore/data-importer/issues/446
 */
class DataObjectLoaderSystemKeyTest extends Unit
{
    private const CLASS_A = 'DiSystemKeyClassA';

    private const CLASS_B = 'DiSystemKeyClassB';

    protected $tester;

    protected function _before(): void
    {
        $this->createClass(self::CLASS_A);
        $this->createClass(self::CLASS_B);
    }

    public function testLoadByKeyIgnoresObjectsOfOtherClasses(): void
    {
        $this->createObject(self::CLASS_B, 'only-in-b');

        $element = (new DataObjectLoader())->loadByAttribute(
            '\\Pimcore\\Model\\DataObject\\' . self::CLASS_A,
            'key',
            'only-in-b',
            '',
            false,
            1
        );

        static::assertNull($element);
    }

    public function testLoadByKeyReturnsObjectOfConfiguredClass(): void
    {
        $this->createObject(self::CLASS_B, 'shared-key');
        $objectA = $this->createObject(self::CLASS_A, 'shared-key');

        $element = (new DataObjectLoader())->loadByAttribute(
            '\\Pimcore\\Model\\DataObject\\' . self::CLASS_A,
            'key',
            'shared-key',
            '',
            false,
            1
        );

        static::assertInstanceOf(Concrete::class, $element);
        static::assertSame($objectA->getId(), $element->getId());
        static::assertSame(self::CLASS_A, $element->getClassName());
    }

    private function createClass(string $name): void
    {
        if (ClassDefinition::getByName($name) instanceof ClassDefinition) {
            return;
        }

        $input = new ClassDefinition\Data\Input();
        $input->setName('title');

        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');
        $panel->addChild($input);

        $definition = new ClassDefinition();
        $definition->setName($name);
        $definition->setLayoutDefinitions($panel);
        $definition->save();
    }

    private function createObject(string $className, string $key): Concrete
    {
        $fqcn = '\\Pimcore\\Model\\DataObject\\' . $className;
        $parent = Service::createFolderByPath('/di-system-key-test/' . $className . '/' . uniqid('', true));

        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent($parent);
        $object->setKey($key);
        $object->setPublished(true);
        $object->save();

        return $object;
    }
}
