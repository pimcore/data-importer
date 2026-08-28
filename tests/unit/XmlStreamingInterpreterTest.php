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
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Psr\Log\NullLogger;
use Symfony\Component\Config\Util\Exception\XmlParsingException;

/**
 * Covers the streaming code path of the XML interpreter: simple absolute element paths are
 * read record by record with XMLReader instead of loading the whole document into a DOM.
 * Complex XPath expressions must keep falling back to the full-DOM path.
 */
class XmlStreamingInterpreterTest extends Unit
{
    protected $tester;

    private const SAMPLE_XML = '<?xml version="1.0" encoding="UTF-8"?>
<catalog>
    <product><sku>A</sku><name>One</name></product>
    <product><sku>B</sku><name>Two</name></product>
    <product><sku>C</sku><name>Three</name></product>
</catalog>';

    private function createInterpreter(string $xpath = '/catalog/product', ?string $schema = null): XmlFileInterpreter
    {
        $interpreter = (new \ReflectionClass(XmlFileInterpreter::class))->newInstanceWithoutConstructor();
        $interpreter->setLogger(new NullLogger());
        $interpreter->setConfigName('test_xml_streaming');
        $interpreter->setExecutionType(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
        $interpreter->setDoDeltaCheck(false);
        $interpreter->setDoCleanup(false);
        $interpreter->setDoArchiveImportFile(false);
        $interpreter->setSettings(['xpath' => $xpath, 'schema' => $schema]);

        return $interpreter;
    }

    private function writeXml(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'di_xml_') . '.xml';
        file_put_contents($path, $content);

        return $path;
    }

    private function invokeStreamingElementPath(XmlFileInterpreter $interpreter): ?array
    {
        $method = new \ReflectionMethod($interpreter, 'getStreamingElementPath');

        return $method->invoke($interpreter);
    }

    /**
     * @return array[]
     */
    private function streamAll(XmlFileInterpreter $interpreter, string $path): array
    {
        $method = new \ReflectionMethod($interpreter, 'streamRecords');

        return iterator_to_array($method->invoke($interpreter, $path), false);
    }

    public function testSimpleAbsolutePathIsStreamable(): void
    {
        $this->assertSame(['catalog', 'product'], $this->invokeStreamingElementPath($this->createInterpreter('/catalog/product')));
    }

    public function testComplexXpathExpressionsAreNotStreamed(): void
    {
        $this->assertNull($this->invokeStreamingElementPath($this->createInterpreter('/catalog/product[1]')));
        $this->assertNull($this->invokeStreamingElementPath($this->createInterpreter('//product')));
        $this->assertNull($this->invokeStreamingElementPath($this->createInterpreter('/catalog/*')));
        $this->assertNull($this->invokeStreamingElementPath($this->createInterpreter('/ns:catalog/ns:product')));
    }

    public function testStreamsMatchingRecords(): void
    {
        $path = $this->writeXml(self::SAMPLE_XML);

        try {
            $rows = $this->streamAll($this->createInterpreter(), $path);

            $this->assertCount(3, $rows);
            $this->assertSame(['sku' => 'A', 'name' => 'One'], $rows[0]);
            $this->assertSame(['sku' => 'C', 'name' => 'Three'], $rows[2]);
        } finally {
            @unlink($path);
        }
    }

    public function testStreamingIgnoresElementsOnOtherPaths(): void
    {
        $xml = '<?xml version="1.0"?>
<catalog>
    <meta><product><sku>NOT-ME</sku></product></meta>
    <product><sku>A</sku></product>
</catalog>';
        $path = $this->writeXml($xml);

        try {
            $rows = $this->streamAll($this->createInterpreter(), $path);

            $this->assertSame([['sku' => 'A']], $rows);
        } finally {
            @unlink($path);
        }
    }

    public function testStreamingMatchesNoRecordsInNamespacedDocument(): void
    {
        // DOMXPath does not match un-prefixed name tests against namespaced elements,
        // the streaming parser has to behave the same way
        $xml = '<?xml version="1.0"?>
<catalog xmlns="http://example.com/ns">
    <product><sku>A</sku></product>
</catalog>';
        $path = $this->writeXml($xml);

        try {
            $this->assertSame([], $this->streamAll($this->createInterpreter(), $path));
        } finally {
            @unlink($path);
        }
    }

    public function testStreamingThrowsOnMalformedXml(): void
    {
        $path = $this->writeXml('<catalog><product><sku>A</sku></product>');

        try {
            $this->expectException(XmlParsingException::class);
            $this->streamAll($this->createInterpreter(), $path);
        } finally {
            @unlink($path);
        }
    }

    public function testStreamingValidatesAgainstConfiguredSchema(): void
    {
        $schema = '<?xml version="1.0"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="catalog">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="product" maxOccurs="unbounded">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="sku" type="xs:string"/>
                            <xs:element name="name" type="xs:string"/>
                        </xs:sequence>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>';

        $validPath = $this->writeXml(self::SAMPLE_XML);
        $invalidPath = $this->writeXml('<?xml version="1.0"?><catalog><unexpected/></catalog>');

        try {
            $this->assertCount(3, $this->streamAll($this->createInterpreter('/catalog/product', $schema), $validPath));

            $this->expectException(XmlParsingException::class);
            $this->streamAll($this->createInterpreter('/catalog/product', $schema), $invalidPath);
        } finally {
            @unlink($validPath);
            @unlink($invalidPath);
        }
    }

    public function testPreviewDataReadsRequestedStreamedRecord(): void
    {
        $path = $this->writeXml(self::SAMPLE_XML);

        try {
            $preview = $this->createInterpreter()->previewData($path, 1);

            $this->assertSame(['sku' => 'B', 'name' => 'Two'], $preview->getRawData());
        } finally {
            @unlink($path);
        }
    }

    public function testPreviewDataFallsBackToLastStreamedRecordWhenOutOfRange(): void
    {
        $path = $this->writeXml(self::SAMPLE_XML);

        try {
            $preview = $this->createInterpreter()->previewData($path, 10);

            $this->assertSame(['sku' => 'C', 'name' => 'Three'], $preview->getRawData());
        } finally {
            @unlink($path);
        }
    }

    public function testComplexXpathStillWorksViaFullDomFallback(): void
    {
        $path = $this->writeXml(self::SAMPLE_XML);

        try {
            $preview = $this->createInterpreter('/catalog/product[sku="B"]')->previewData($path, 0);

            $this->assertSame(['sku' => 'B', 'name' => 'Two'], $preview->getRawData());
        } finally {
            @unlink($path);
        }
    }
}
