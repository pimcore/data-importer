<?php
declare(strict_types=1);

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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SpreadsheetCell;

class AdHocSpreadsheetCellTest extends Unit
{
    /**
     * @dataProvider formulas
     */
    public function testTextASpreadsheetWouldCalculateIsMarkedAsText(string $text): void
    {
        $this->assertSame("'" . $text, SpreadsheetCell::safe($text));
    }

    public static function formulas(): array
    {
        return [
            'equals' => ['=HYPERLINK("https://example.org")'],
            'plus' => ['+1+cmd'],
            'minus' => ['-1+cmd'],
            'at' => ['@SUM(A1)'],
            'tab' => ["\tvalue"],
            'carriage return' => ["\rvalue"],
            'after a space' => [' =1+1'],
            'after a control character' => ["\x01=1+1"],
        ];
    }

    /**
     * @dataProvider plainTexts
     */
    public function testAnythingElseStaysAsItIs(string $text): void
    {
        $this->assertSame($text, SpreadsheetCell::safe($text));
    }

    public static function plainTexts(): array
    {
        return [
            'text' => ['Chair'],
            'empty' => [''],
            'a negative number' => ['-12.5'],
            'a signed number' => ['+7'],
            'a mark inside' => ['a=b'],
        ];
    }
}
