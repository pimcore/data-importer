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
use Pimcore\Bundle\DataImporterBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The cell count above which the XLSX interpreter reads the sheet in row windows is a
 * documented setting, not a guess the interpreter makes for itself.
 */
class XlsxInterpreterConfigurationTest extends Unit
{
    protected $tester;

    private const NODE = 'xlsx_interpreter';

    private const THRESHOLD = 'windowed_read_cell_threshold';

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    public function testTheDefaultKeepsTodaysWholeWorkbookReadForSmallFiles(): void
    {
        $config = $this->process([]);

        $this->assertSame(200000, $config[self::NODE][self::THRESHOLD]);
    }

    public function testTheThresholdCanBeConfigured(): void
    {
        $config = $this->process([self::NODE => [self::THRESHOLD => 50000]]);

        $this->assertSame(50000, $config[self::NODE][self::THRESHOLD]);
    }

    public function testANegativeThresholdIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([self::NODE => [self::THRESHOLD => -1]]);
    }
}
