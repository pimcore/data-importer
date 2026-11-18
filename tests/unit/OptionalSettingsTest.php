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
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XmlFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\SqlLoader;

/**
 * An SQL loader's `where` and `groupBy` and an XML interpreter's `schema` are optional settings: leaving them out
 * must neither warn nor throw.
 */
class OptionalSettingsTest extends Unit
{
    protected $tester;

    private const SQL_REQUIRED = ['connection' => 'default', 'select' => 'id', 'from' => 'objects'];

    private function assertNoWarnings(callable $callable): void
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $callable();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
    }

    private function readProperty(object $object, string $name): mixed
    {
        return (new \ReflectionProperty($object, $name))->getValue($object);
    }

    public function testSqlLoaderWithoutWhereAndGroupBy(): void
    {
        $loader = (new \ReflectionClass(SqlLoader::class))->newInstanceWithoutConstructor();

        $this->assertNoWarnings(static fn () => $loader->setSettings(self::SQL_REQUIRED));

        $this->assertSame('', $this->readProperty($loader, 'where'));
        $this->assertSame('', $this->readProperty($loader, 'groupBy'));
    }

    public function testSqlLoaderKeepsWhereAndGroupBy(): void
    {
        $loader = (new \ReflectionClass(SqlLoader::class))->newInstanceWithoutConstructor();

        $loader->setSettings(self::SQL_REQUIRED + ['where' => 'id > 1', 'groupBy' => 'type']);

        $this->assertSame('id > 1', $this->readProperty($loader, 'where'));
        $this->assertSame('type', $this->readProperty($loader, 'groupBy'));
    }

    public function testXmlInterpreterWithoutSchema(): void
    {
        $interpreter = (new \ReflectionClass(XmlFileInterpreter::class))->newInstanceWithoutConstructor();

        $this->assertNoWarnings(static fn () => $interpreter->setSettings(['xpath' => '/Items/Item']));

        $this->assertNull($this->readProperty($interpreter, 'schema'));
    }

    public function testXmlInterpreterKeepsSchema(): void
    {
        $interpreter = (new \ReflectionClass(XmlFileInterpreter::class))->newInstanceWithoutConstructor();

        $interpreter->setSettings(['xpath' => '/Items/Item', 'schema' => '<xs:schema/>']);

        $this->assertSame('<xs:schema/>', $this->readProperty($interpreter, 'schema'));
    }
}
