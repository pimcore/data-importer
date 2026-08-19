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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Mcp\Tool\Traits;

use Pimcore\Bundle\StudioBackendBundle\Mcp\Exception\InvalidMcpToolArgumentException;

use Codeception\Test\Unit;
use InvalidArgumentException;
use JsonException;
use Pimcore\Bundle\DataImporterBundle\Mcp\Tool\Traits\ConfigurationParserTrait;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * The trait behind the `configuration` and `format` arguments of validate_import_config,
 * enrich_import_config and save_import_config. Exercised directly, because the three tools all
 * depend on the same auto-detection and each only sees the parsed array.
 *
 * @internal
 */
final class ConfigurationParserTraitTest extends Unit
{
    use ConfigurationParserTrait;

    private const string JSON_BODY = '{"general": {"name": "csv-car-import"}, "mappingConfig": []}';
    private const string MSG_NOT_AN_OBJECT = 'must be an object';


    private const string YAML_BODY = "general:\n    name: csv-car-import\nmappingConfig: []\n";

    /**
     * @return array<string, mixed>
     */
    private const array PARSED = ['general' => ['name' => 'csv-car-import'], 'mappingConfig' => []];

    public function testJsonIsDetectedFromALeadingBrace(): void
    {
        $this->assertSame(self::PARSED, $this->parseConfiguration(self::JSON_BODY, ''));
    }

    public function testJsonIsDetectedFromALeadingBracketAndThenRejectedAsAList(): void
    {
        // Detection has to see the bracket as JSON; a top level list is still not a configuration.
        $this->expectException(InvalidMcpToolArgumentException::class);
        $this->expectExceptionMessage(self::MSG_NOT_AN_OBJECT);

        $this->parseConfiguration('[{"label": "Name"}]', '');
    }

    public function testDetectionIgnoresLeadingWhitespace(): void
    {
        // Models emit fenced payloads, so a leading newline is the normal case, not the exception.
        $this->assertSame(self::PARSED, $this->parseConfiguration("\n  \t" . self::JSON_BODY, ''));
    }

    public function testYamlIsDetectedWhenTheBodyStartsWithAnythingElse(): void
    {
        $this->assertSame(self::PARSED, $this->parseConfiguration(self::YAML_BODY, ''));
    }

    public function testAnExplicitJsonFormatOverridesDetection(): void
    {
        // A YAML list would auto-detect as YAML and parse; naming json has to win instead.
        $this->expectException(InvalidMcpToolArgumentException::class);
        $this->expectExceptionMessage('not valid JSON');

        $this->parseConfiguration("- label: Name\n", 'json');
    }

    public function testAnExplicitYamlFormatOverridesDetection(): void
    {
        // A YAML flow mapping auto-detects as JSON on its leading brace and would fail to decode.
        $this->assertSame(['general' => ['name' => 'x']], $this->parseConfiguration('{general: {name: x}}', 'yaml'));
    }

    public function testTheFormatIsMatchedCaseInsensitively(): void
    {
        $this->assertSame(self::PARSED, $this->parseConfiguration(self::JSON_BODY, 'JSON'));
    }

    public function testAnUnrecognisedFormatIsRejectedRatherThanGuessed(): void
    {
        // Nothing validates the enum before the tool runs. Silently parsing as YAML meant an
        // explicit format that was simply wrong changed the result without saying so.
        $this->expectException(InvalidMcpToolArgumentException::class);
        $this->expectExceptionMessage('Unknown format "xml"');

        $this->parseConfiguration(self::YAML_BODY, 'xml');
    }

    public function testMalformedJsonIsReportedAsAnInvalidArgument(): void
    {
        // The body is the caller's own payload, so the reason must survive the error boundary
        // instead of being genericised into "internal error, see the log".
        $this->expectException(InvalidMcpToolArgumentException::class);
        $this->expectExceptionMessage('not valid JSON');

        $this->parseConfiguration('{"general": ', '');
    }

    public function testMalformedYamlIsReportedAsAnInvalidArgument(): void
    {
        $this->expectException(InvalidMcpToolArgumentException::class);
        $this->expectExceptionMessage('not valid YAML');

        $this->parseConfiguration("general:\n  name: x\n bad indentation\n", 'yaml');
    }

    public function testAYamlScalarIsRejectedBecauseAConfigurationIsAnArray(): void
    {
        $this->expectException(InvalidMcpToolArgumentException::class);
        $this->expectExceptionMessage(self::MSG_NOT_AN_OBJECT);

        $this->parseConfiguration('just a string', '');
    }

    public function testAnEmptyBodyIsRejectedAsANonArray(): void
    {
        $this->expectException(InvalidMcpToolArgumentException::class);
        $this->expectExceptionMessage(self::MSG_NOT_AN_OBJECT);

        $this->parseConfiguration('', '');
    }
}
