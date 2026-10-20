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

/**
 * Regression coverage for https://github.com/pimcore/data-importer/issues/524
 *
 * Text such as `603E709` was typed as float(INF) by the XML conversion, which broke the JSON encoding of the
 * import row, and `0123` silently became the integer 83 (octal). Values must keep their text whenever
 * typing them would change it.
 */
class XmlFileInterpreterTypingTest extends Unit
{
    private const KEY = 'Code';

    protected $tester;

    private function previewRaw(string $xml): array
    {
        $path = tempnam(sys_get_temp_dir(), 'di_xml_') . '.xml';
        file_put_contents($path, $xml);

        try {
            $interpreter = (new \ReflectionClass(XmlFileInterpreter::class))->newInstanceWithoutConstructor();
            $interpreter->setSettings(['xpath' => '/Items/Item', 'schema' => null]);

            return $interpreter->previewData($path)->getRawData();
        } finally {
            unlink($path);
        }
    }

    public static function lossyValues(): array
    {
        return [
            'exponent overflows to INF' => ['603E709'],
            'exponent' => ['1E5'],
            'leading zero is not octal' => ['0123'],
            'negative leading zero' => ['-0123'],
            'hex' => ['0x1F'],
            'binary' => ['0b101'],
            'trailing zero' => ['12.50'],
            'explicit plus sign' => ['+5'],
        ];
    }

    /**
     * @dataProvider lossyValues
     */
    public function testLossyValuesKeepTheirText(string $value): void
    {
        $raw = $this->previewRaw('<Items><Item><Code>' . $value . '</Code></Item></Items>');

        $this->assertSame($value, $raw[self::KEY]);
        $this->assertNotFalse(json_encode($raw));
    }

    public function testLossyAttributeKeepsItsText(): void
    {
        $raw = $this->previewRaw('<Items><Item code="603E709">x</Item></Items>');

        $this->assertSame('603E709', $raw['code']);
        $this->assertNotFalse(json_encode($raw));
    }

    public static function losslessValues(): array
    {
        return [
            'int' => ['42', 42],
            'zero' => ['0', 0],
            'negative int' => ['-7', -7],
            'float' => ['1.5', 1.5],
            'bool' => ['true', true],
            'null' => ['null', null],
            'text' => ['abc', 'abc'],
        ];
    }

    /**
     * @dataProvider losslessValues
     */
    public function testLosslessValuesAreStillTyped(string $value, mixed $expected): void
    {
        $raw = $this->previewRaw('<Items><Item><Code>' . $value . '</Code></Item></Items>');

        $this->assertSame($expected, $raw[self::KEY]);
    }
}
