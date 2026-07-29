<?php declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

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
        $fallbackPath = (new \ReflectionObject($strategy))->getProperty('fallbackPath');
        $fallbackPath->setAccessible(true);

        $strategy->setSettings([
            'dataSourceIndex' => 1,
            'findStrategy' => 'path',
        ]);

        self::assertNull($fallbackPath->getValue($strategy));
    }

    /**
     * @dataProvider provideStrategies
     */
    public function testFallbackPathIsAssigned(string $strategyClass): void
    {
        $strategy = new $strategyClass(new DataObjectLoader());
        $fallbackPath = (new \ReflectionObject($strategy))->getProperty('fallbackPath');
        $fallbackPath->setAccessible(true);

        $strategy->setSettings([
            'dataSourceIndex' => 1,
            'findStrategy' => 'path',
            'fallbackPath' => '/fallback',
        ]);

        self::assertSame('/fallback', $fallbackPath->getValue($strategy));
    }
}
