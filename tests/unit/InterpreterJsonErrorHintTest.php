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
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidInputException;
use Psr\Log\NullLogger;

/**
 * The "make sure the source file is UTF-8 encoded" hint of the JSON encoding error in
 * AbstractInterpreter::processImportRow must only appear for JSON_ERROR_UTF8; other encoding errors
 * (e.g. INF from a mis-typed number, see issue #524) must not point the user at the file encoding.
 */
class InterpreterJsonErrorHintTest extends Unit
{
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

    private function processRow(array $row): void
    {
        $interpreter = (new \ReflectionClass(CsvFileInterpreter::class))->newInstanceWithoutConstructor();
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName('test_json_hint');
        $interpreter->setDoDeltaCheck(false);
        $interpreter->setDoCleanup(false);

        (new \ReflectionMethod($interpreter, 'processImportRow'))->invoke($interpreter, $row);
    }

    public function testInvalidUtf8NestedInRowGetsUtf8Hint(): void
    {
        // nested value: not reached by the top-level mb_check_encoding() check, so json_encode() fails
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessageMatches('/Malformed UTF-8.*Please make sure the source file is UTF-8 encoded\./');

        $this->processRow(['Code' => ['child' => "bad\xB2"]]);
    }

    public function testInfinityErrorHasNoUtf8Hint(): void
    {
        try {
            $this->processRow(['Code' => INF]);
            $this->fail('Expected an InvalidInputException');
        } catch (InvalidInputException $e) {
            $this->assertStringContainsString('Inf and NaN cannot be JSON encoded', $e->getMessage());
            $this->assertStringNotContainsString('UTF-8', $e->getMessage());
        }
    }
}
