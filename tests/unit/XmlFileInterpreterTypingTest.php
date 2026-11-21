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
use Pimcore\Bundle\DataImporterBundle\Resolver\Load\LoadStrategyInterface;
use Pimcore\Bundle\DataImporterBundle\Resolver\Resolver;
use Psr\Log\NullLogger;

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

    use NoCurrentUserKernelTrait;

    protected $tester;

    protected function _before(): void
    {
        $this->installNoCurrentUserKernel();
    }

    protected function _after(): void
    {
        $this->removeKernel();
    }

    private function previewRaw(string $xml): array
    {
        $base = tempnam(sys_get_temp_dir(), 'di_xml_');
        $path = $base . '.xml';
        file_put_contents($path, $xml);

        try {
            $interpreter = (new \ReflectionClass(XmlFileInterpreter::class))->newInstanceWithoutConstructor();
            $interpreter->setSettings(['xpath' => '/Items/Item', 'schema' => null]);

            return $interpreter->previewData($path)->getRawData();
        } finally {
            unlink($path);
            unlink($base);
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

    /**
     * The import path (doInterpretFileAndCallProcessRow) converts rows separately from the preview. QueueService,
     * DeltaChecker and Resolver are final and cannot be doubled, so the spy is the resolver's load strategy:
     * with cleanup enabled processImportRow hands it the row first, before anything that needs a booted kernel.
     *
     * @dataProvider lossyValues
     */
    public function testImportPathKeepsLossyValuesAsText(string $value): void
    {
        $rows = [];
        $strategy = $this->createStub(LoadStrategyInterface::class);
        $strategy->method('extractIdentifierFromData')->willReturnCallback(
            static function (array $row) use (&$rows) {
                $rows[] = $row;

                return $row[self::KEY];
            }
        );

        $resolver = (new \ReflectionClass(Resolver::class))->newInstanceWithoutConstructor();
        $resolver->setLoadingStrategy($strategy);

        $base = tempnam(sys_get_temp_dir(), 'di_xml_');
        $path = $base . '.xml';
        file_put_contents($path, '<Items><Item><Code>' . $value . '</Code></Item></Items>');

        try {
            $interpreter = (new \ReflectionClass(XmlFileInterpreter::class))->newInstanceWithoutConstructor();
            $interpreter->setLogger(new NullLogger());
            $interpreter->setConfigName('test_xml_typing');
            $interpreter->setDoDeltaCheck(false);
            $interpreter->setDoCleanup(true);
            $interpreter->setResolver($resolver);
            $interpreter->setSettings(['xpath' => '/Items/Item', 'schema' => null]);

            $reset = new \ReflectionMethod($interpreter, 'resetIdentifierCache');
            $reset->invoke($interpreter);
            $import = new \ReflectionMethod($interpreter, 'doInterpretFileAndCallProcessRow');

            try {
                $import->invoke($interpreter, $path);
            } catch (\Error) {
                // expected: after the spy, processImportRow needs the booted kernel (current admin user)
            }
        } finally {
            unlink($path);
            unlink($base);
        }

        $this->assertSame([[self::KEY => $value]], $rows);
    }
}
