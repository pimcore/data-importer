<?php declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\IdStrategy;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;

class AbstractLoadTest extends Unit
{
    protected $tester;

    private function createStrategy(mixed $dataSourceIndex): IdStrategy
    {
        $strategy = new IdStrategy($this->createMock(Connection::class), new DataObjectLoader());
        $strategy->setSettings(['dataSourceIndex' => $dataSourceIndex]);

        return $strategy;
    }

    public function testReturnsValueWhenIdentifierIsSet(): void
    {
        $strategy = $this->createStrategy(0);

        static::assertSame('abc', $strategy->extractIdentifierFromData([0 => 'abc']));
    }

    public function testReturnsNullWhenIdentifierColumnIsEmpty(): void
    {
        $strategy = $this->createStrategy(0);

        static::assertNull($strategy->extractIdentifierFromData([0 => null]));
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

        static::assertNull($strategy->loadElement([0 => null]));
    }
}
