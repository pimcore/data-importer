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
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\AttributeStrategy;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * @see https://github.com/pimcore/data-importer/issues/284
 */
class AttributeStrategyPathTest extends Unit
{
    private const CLASS_NAME = 'DiAttributePathClass';

    private const CLASS_NAMESPACE = '\\Pimcore\\Model\\DataObject\\';

    private const ROOT = '/di-attribute-path-test';

    protected $tester;

    private ClassDefinition $class;

    protected function _before(): void
    {
        $this->class = $this->createClass(self::CLASS_NAME);
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->class->delete();
    }

    public function testLoadWithoutPathReturnsFirstMatchOfWholeTree(): void
    {
        $vendorA = $this->createObject('vendor-a', 'SKU-1');
        $vendorB = $this->createObject('vendor-b', 'SKU-1');

        $element = $this->createStrategy([])->loadElementByIdentifier('SKU-1');

        // without a path the first match of the whole tree wins, whichever vendor the database returns first
        static::assertContains($element?->getId(), [$vendorA->getId(), $vendorB->getId()]);
    }

    public function testLoadWithPathIgnoresSameAttributeValueOutsideOfPath(): void
    {
        $this->createObject('vendor-a', 'SKU-1');
        $vendorB = $this->createObject('vendor-b', 'SKU-1');

        $element = $this->createStrategy(['path' => self::ROOT . '/vendor-b'])->loadElementByIdentifier('SKU-1');

        static::assertSame($vendorB->getId(), $element?->getId());
    }

    public function testLoadWithPathFindsObjectsInSubFolders(): void
    {
        $nested = $this->createObject('vendor-a/2026/spring', 'SKU-2');

        $element = $this->createStrategy(['path' => self::ROOT . '/vendor-a/'])->loadElementByIdentifier('SKU-2');

        static::assertSame($nested->getId(), $element?->getId());
    }

    public function testLoadWithPathDoesNotMatchSiblingFolderWithSamePrefix(): void
    {
        $this->createObject('vendor-ab', 'SKU-3');

        $element = $this->createStrategy(['path' => self::ROOT . '/vendor-a'])->loadElementByIdentifier('SKU-3');

        static::assertNull($element);
    }

    public function testLoadWithPathTreatsWildcardsLiterally(): void
    {
        $this->createObject('vendor-a', 'SKU-4');

        $element = $this->createStrategy(['path' => self::ROOT . '/vendor-%'])->loadElementByIdentifier('SKU-4');

        static::assertNull($element);
    }

    public function testLoadByKeyWithPath(): void
    {
        $this->createObject('vendor-a', 'SKU-5', 'same-key');
        $vendorB = $this->createObject('vendor-b', 'SKU-6', 'same-key');

        $element = $this->createStrategy(['attributeName' => 'key', 'path' => self::ROOT . '/vendor-b'])
            ->loadElementByIdentifier('same-key');

        static::assertSame($vendorB->getId(), $element?->getId());
    }

    public function testLoadWithPathAndEmptyIdentifierUsesListRoute(): void
    {
        $this->createObject('vendor-a', null);
        $vendorB = $this->createObject('vendor-b', null);

        $element = $this->createStrategy(['path' => self::ROOT . '/vendor-b'])->loadElementByIdentifier('');

        static::assertSame($vendorB->getId(), $element?->getId());
    }

    public function testLoadWithPathByHandWrittenStaticGetterRespectsPath(): void
    {
        $vendorA = $this->createObject('vendor-a', 'SKU-11');

        $strategy = $this->createStrategy(['attributeName' => 'id', 'path' => self::ROOT . '/vendor-b']);
        static::assertNull($strategy->loadElementByIdentifier((string) $vendorA->getId()));

        $strategy = $this->createStrategy(['attributeName' => 'id', 'path' => self::ROOT . '/vendor-a']);
        static::assertSame($vendorA->getId(), $strategy->loadElementByIdentifier((string) $vendorA->getId())?->getId());
    }

    public function testFullIdentifierListIsLimitedToPath(): void
    {
        $this->createObject('vendor-a', 'SKU-A');
        $this->createObject('vendor-b', 'SKU-B');

        static::assertEqualsCanonicalizing(
            ['SKU-A', 'SKU-B'],
            $this->createStrategy([])->loadFullIdentifierList()
        );
        static::assertSame(
            ['SKU-B'],
            $this->createStrategy(['path' => self::ROOT . '/vendor-b'])->loadFullIdentifierList()
        );
    }

    public function testLocalizedLoadWithPathIgnoresSameValueOutsideOfPath(): void
    {
        $this->createObject('vendor-a', 'SKU-7', null, 'Title-1');
        $vendorB = $this->createObject('vendor-b', 'SKU-8', null, 'Title-1');

        $element = $this->createStrategy([
            'attributeName' => 'title',
            'language' => 'en',
            'path' => self::ROOT . '/vendor-b',
        ])->loadElementByIdentifier('Title-1');

        static::assertSame($vendorB->getId(), $element?->getId());
    }

    public function testLocalizedFullIdentifierListIsLimitedToPath(): void
    {
        $this->createObject('vendor-a', 'SKU-9', null, 'Title-A');
        $this->createObject('vendor-b', 'SKU-10', null, 'Title-B');

        static::assertSame(
            ['Title-B'],
            $this->createStrategy([
                'attributeName' => 'title',
                'language' => 'en',
                'path' => self::ROOT . '/vendor-b',
            ])->loadFullIdentifierList()
        );
    }

    private function createStrategy(array $settings): AttributeStrategy
    {
        $strategy = new AttributeStrategy(Db::get(), new DataObjectLoader());
        $strategy->setDataObjectClassId($this->class->getId());
        $strategy->setSettings($settings + ['dataSourceIndex' => 0, 'attributeName' => 'sku']);

        return $strategy;
    }

    private function createClass(string $name): ClassDefinition
    {
        $existing = ClassDefinition::getByName($name);
        if ($existing instanceof ClassDefinition) {
            return $existing;
        }

        $input = new ClassDefinition\Data\Input();
        $input->setName('sku');
        $input->setIndex(true);

        $title = new ClassDefinition\Data\Input();
        $title->setName('title');
        $title->setIndex(true);

        $localizedFields = new ClassDefinition\Data\Localizedfields();
        $localizedFields->setName('localizedfields');
        $localizedFields->addChild($title);

        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');
        $panel->addChild($input);
        $panel->addChild($localizedFields);

        $definition = new ClassDefinition();
        $definition->setName($name);
        $definition->setLayoutDefinitions($panel);
        $definition->save();

        return $definition;
    }

    private function createObject(string $folder, ?string $sku, ?string $key = null, ?string $title = null): Concrete
    {
        $fqcn = self::CLASS_NAMESPACE . self::CLASS_NAME;
        $parent = Service::createFolderByPath(self::ROOT . '/' . $folder);

        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent($parent);
        $object->setKey($key ?? uniqid('o', true));
        $object->setPublished(true);
        $object->setSku($sku);
        if ($title !== null) {
            $object->setTitle($title, 'en');
        }
        $object->save();

        return $object;
    }
}
