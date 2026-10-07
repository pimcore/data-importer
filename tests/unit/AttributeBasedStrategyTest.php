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
use Pimcore\Bundle\DataImporterBundle\Resolver\Publish\AttributeBasedStrategy;

class AttributeBasedStrategyTest extends Unit
{
    protected $tester;

    public function provideIndexes(): array
    {
        return [
            ['0'],
            [0],
            [1],
            ['12'],
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
