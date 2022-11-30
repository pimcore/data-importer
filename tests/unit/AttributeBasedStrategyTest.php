<?php declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\AttributeBasedStrategy;

class AttributeBasedStrategyTest extends \Codeception\Test\Unit
{
    protected $tester;

    public function provideIndexes(): array
    {
        return [
            ["0"],
            [0],
            [1],
            ["12"],
        ];
    }

    /**
     * @dataProvider provideIndexes
     */
    public function testDataSourceIndex(mixed $index): void
    {
        $config = ['dataSourceIndex' => $index];
        $strategy = new AttributeBasedStrategy();
        $dataSourceIndex = (new \ReflectionObject($strategy))->getProperty('dataSourceIndex');
        $dataSourceIndex->setAccessible(true);

        $strategy->setSettings($config);
        $result = $dataSourceIndex->getValue($strategy);
        self::assertEquals($index, $result);
    }
}
