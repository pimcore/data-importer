<?php declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Closure;
use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\FindOrCreateFolderStrategy;
use Pimcore\Bundle\DataImporterBundle\Resolver\Location\FindParentStrategy;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;

class LocationStrategyFallbackPathTest extends Unit
{
    protected $tester;

    public function provideStrategies(): array
    {
        return [
            [FindParentStrategy::class],
            [FindOrCreateFolderStrategy::class],
        ];
    }

    /**
     * @dataProvider provideStrategies
     */
    public function testMissingFallbackPathIsAllowed(string $strategyClass): void
    {
        $strategy = new $strategyClass(new DataObjectLoader());

        $strategy->setSettings([
            'dataSourceIndex' => 1,
            'findStrategy' => 'path',
        ]);

        static::assertNull($this->readFallbackPath($strategy));
    }

    /**
     * @dataProvider provideStrategies
     */
    public function testFallbackPathIsAssigned(string $strategyClass): void
    {
        $strategy = new $strategyClass(new DataObjectLoader());

        $strategy->setSettings([
            'dataSourceIndex' => 1,
            'findStrategy' => 'path',
            'fallbackPath' => '/fallback',
        ]);

        static::assertSame('/fallback', $this->readFallbackPath($strategy));
    }

    private function readFallbackPath(object $strategy): ?string
    {
        return Closure::bind(function () {
            return $this->fallbackPath;
        }, $strategy, $strategy::class)();
    }
}
