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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException as SchemaException;
use Symfony\Component\Config\Definition\Processor;

/**
 * fgetcsv() and explode() throw a ValueError for character settings they cannot use, which
 * reaches the user as an unexplained 500 on preview. A configuration written programmatically
 * hits this easily - a JSON encoded backslash escape is one character, a double encoded one is
 * two - so both the schema and the interpreter have to reject the value instead.
 */
class CsvInterpreterSettingsTest extends Unit
{
    /**
     * @var \Pimcore\Bundle\DataImporterBundle\Tests\UnitTester
     */
    protected $tester;

    private const string DOUBLE_ESCAPED_BACKSLASH = '\\\\';

    private function interpreter(): CsvFileInterpreter
    {
        return $this->tester->grabService(CsvFileInterpreter::class);
    }

    /**
     * The path an MCP written configuration takes: the settings are checked against the schema
     * before anything is stored, so a value fgetcsv() cannot use never reaches it.
     */
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

    public function testTheEscapeValuesFgetcsvAcceptsAreLetThrough(): void
    {
        // A single character and empty are exactly what fgetcsv() accepts, so neither may be
        // mistaken for a value to reject. Empty in particular must not read as missing.
        $this->expectNotToPerformAssertions();

        $this->interpreter()->setSettings(['escape' => '\\']);
        $this->interpreter()->setSettings(['escape' => '']);
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
        $this->expectException(SchemaException::class);
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
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('it must be exactly one character');

        $this->processCsvSettings(['delimiter' => '\\t']);
    }

    public function testTheDefaultsAreThemselvesValidCharacters(): void
    {
        // A configuration that omits the character settings has to keep working.
        $settings = $this->processCsvSettings([]);

        $this->assertSame(',', $settings['delimiter']);
        $this->assertSame('"', $settings['enclosure']);
        $this->assertSame('\\', $settings['escape']);
    }

    public function testTheExplodeSchemaRejectsAnEmptyDelimiter(): void
    {
        // explode() throws a ValueError on an empty separator.
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('The explode delimiter must not be empty.');

        $this->processExplodeSettings(['delimiter' => '']);
    }

    public function testTheExplodeDelimiterDefaultsToASpace(): void
    {
        $this->assertSame(' ', $this->processExplodeSettings([])['delimiter']);
    }

    private function processExplodeSettings(array $settings): array
    {
        $explode = $this->tester->grabService(Explode::class);

        return (new Processor())->process($explode->getConfigTreeBuilder()->buildTree(), [$settings]);
    }
}
