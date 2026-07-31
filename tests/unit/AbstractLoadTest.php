<?php declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\IdStrategy;

class AbstractLoadTest extends Unit
{
    protected $tester;

    private function createStrategy(mixed $dataSourceIndex): IdStrategy
    {
        $strategy = (new \ReflectionClass(IdStrategy::class))->newInstanceWithoutConstructor();

        $dataSourceIndexProperty = (new \ReflectionObject($strategy))->getProperty('dataSourceIndex');
        $dataSourceIndexProperty->setAccessible(true);
        $dataSourceIndexProperty->setValue($strategy, $dataSourceIndex);

        return $strategy;
    }

    public function testReturnsValueWhenIdentifierIsSet(): void
    {
        $strategy = $this->createStrategy(0);

        self::assertSame('abc', $strategy->extractIdentifierFromData([0 => 'abc']));
    }

    public function testReturnsNullWhenIdentifierColumnIsEmpty(): void
    {
        $strategy = $this->createStrategy(0);

        self::assertNull($strategy->extractIdentifierFromData([0 => null]));
    }

    public function testThrowsExceptionWhenIdentifierColumnIsMissing(): void
    {
        $strategy = $this->createStrategy(0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier not set.');

        $strategy->extractIdentifierFromData([]);
    }

    public function testLoadElementReturnsNullForEmptyIdentifierInsteadOfTypeError(): void
    {
        $strategy = $this->createStrategy(0);

        self::assertNull($strategy->loadElement([0 => null]));
    }
}
