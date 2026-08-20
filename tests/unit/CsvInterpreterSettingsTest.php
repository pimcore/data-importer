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
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\Explode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException as SymfonyInvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * fgetcsv() and explode() throw a ValueError for character settings they cannot use, which
 * reaches the user as an unexplained 500 on preview. A configuration written programmatically
 * hits this easily - a JSON encoded backslash escape is one character, a double encoded one is
 * two - so both the schema and the interpreter have to reject the value instead.
 */
class CsvInterpreterSettingsTest extends Unit
{
    protected $tester;

    private const DOUBLE_ESCAPED_BACKSLASH = '\\\\';

    private function interpreter(): CsvFileInterpreter
    {
        return (new \ReflectionClass(CsvFileInterpreter::class))->newInstanceWithoutConstructor();
    }

    private function processCsvSettings(array $settings): array
    {
        return (new Processor())->process(
            $this->interpreter()->getConfigTreeBuilder()->buildTree(),
            [$settings]
        );
    }

    public function testATwoCharacterEscapeIsRejectedWithTheOffendingValue(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The CSV `escape` must be empty or exactly one character');

        $this->interpreter()->setSettings(['escape' => self::DOUBLE_ESCAPED_BACKSLASH]);
    }

    public function testASingleBackslashEscapeStaysAccepted(): void
    {
        $interpreter = $this->interpreter();
        $interpreter->setSettings(['escape' => '\\']);

        $this->assertSame('\\', $this->readSetting($interpreter, 'escape'));
    }

    public function testAnEmptyEscapeIsAccepted(): void
    {
        // Empty is what fgetcsv() itself asks for, so it must not be treated as missing.
        $interpreter = $this->interpreter();
        $interpreter->setSettings(['escape' => '']);

        $this->assertSame('', $this->readSetting($interpreter, 'escape'));
    }

    public function testAMissingEscapeFallsBackToTheDefault(): void
    {
        $interpreter = $this->interpreter();
        $interpreter->setSettings([]);

        $this->assertSame('\\', $this->readSetting($interpreter, 'escape'));
        $this->assertSame(',', $this->readSetting($interpreter, 'delimiter'));
        $this->assertSame('"', $this->readSetting($interpreter, 'enclosure'));
    }

    public function testAMultiCharacterDelimiterIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The CSV `delimiter` must be exactly one character');

        $this->interpreter()->setSettings(['delimiter' => '||']);
    }

    public function testAnEmptyDelimiterIsRejectedRatherThanDefaulted(): void
    {
        // Unlike the escape, fgetcsv() has no meaning for an empty delimiter.
        $this->expectException(InvalidConfigurationException::class);

        $this->interpreter()->setSettings(['delimiter' => '']);
    }

    public function testAMultiCharacterEnclosureIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The CSV `enclosure` must be exactly one character');

        $this->interpreter()->setSettings(['enclosure' => '""']);
    }

    public function testTheSchemaRejectsATwoCharacterEscapeBeforeItCanBeStored(): void
    {
        // The path an MCP written configuration takes: caught at validation, so the broken
        // value never reaches fgetcsv() in the first place.
        $this->expectException(SymfonyInvalidConfigurationException::class);
        $this->expectExceptionMessage('it must be empty or exactly one character');

        $this->processCsvSettings(['escape' => self::DOUBLE_ESCAPED_BACKSLASH]);
    }

    public function testTheSchemaAcceptsTheSettingsAWorkingConfigurationUses(): void
    {
        $settings = $this->processCsvSettings([
            'skipFirstRow' => true,
            'delimiter' => ';',
            'enclosure' => '"',
            'escape' => '',
        ]);

        $this->assertSame(';', $settings['delimiter']);
        $this->assertSame('', $settings['escape']);
    }

    public function testTheSchemaRejectsAMultiCharacterDelimiter(): void
    {
        $this->expectException(SymfonyInvalidConfigurationException::class);
        $this->expectExceptionMessage('it must be exactly one character');

        $this->processCsvSettings(['delimiter' => '\\t']);
    }

    public function testTheExplodeSchemaRejectsAnEmptyDelimiter(): void
    {
        // explode() throws a ValueError on an empty separator.
        $this->expectException(SymfonyInvalidConfigurationException::class);
        $this->expectExceptionMessage('The explode delimiter must not be empty.');

        $explode = (new \ReflectionClass(Explode::class))->newInstanceWithoutConstructor();

        (new Processor())->process(
            $explode->getConfigTreeBuilder()->buildTree(),
            [['delimiter' => '']]
        );
    }

    private function readSetting(CsvFileInterpreter $interpreter, string $property): string
    {
        $reflectionProperty = new \ReflectionProperty(CsvFileInterpreter::class, $property);

        return $reflectionProperty->getValue($interpreter);
    }
}
