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
use OpenApi\Attributes\Property;
use OpenApi\Generator;
use Pimcore\Bundle\DataImporterBundle\Schema\UnitDataResponse;
use ReflectionClass;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class UnitDataResponseTest extends Unit
{
    protected $tester;

    private const string SNAPSHOT_PATH = __DIR__ . '/../../assets/studio/build/api/docs.jsonopenapi.json';

    private const string GENERATED_CLIENT_PATH =
        __DIR__ . '/../../assets/studio/js/src/modules/data-importer/data-importer-api-slice.gen.ts';

    private const string SNAPSHOT_SCHEMA_NAME = 'BundleDataImporterUnitDataResponse';

    private function serialize(UnitDataResponse $response): array
    {
        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        return json_decode($serializer->serialize($response, 'json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return string[]
     */
    private function documentedPropertyNames(): array
    {
        $constructor = (new ReflectionClass(UnitDataResponse::class))->getConstructor();
        $this->assertNotNull($constructor);

        $documented = [];
        foreach ($constructor->getParameters() as $parameter) {
            foreach ($parameter->getAttributes(Property::class) as $attribute) {
                $property = $attribute->newInstance()->property;
                $documented[] = Generator::isDefault($property) ? $parameter->getName() : $property;
            }
        }

        return $documented;
    }

    public function testSerializesUnitListUnderTheDocumentedPropertyName(): void
    {
        $data = $this->serialize(new UnitDataResponse([['unitId' => 'kg', 'abbreviation' => 'kg']]));

        $this->assertArrayHasKey('unitList', $data);
        $this->assertSame([['unitId' => 'kg', 'abbreviation' => 'kg']], $data['unitList']);
    }

    /**
     * The Studio UI reads the response through the OpenAPI-generated client, so the documented
     * property name has to be the one the serializer actually emits. When the two drifted apart,
     * the quantity value transformer's unit select silently rendered empty.
     */
    public function testDocumentedPropertyNameMatchesTheSerializedKey(): void
    {
        $documented = $this->documentedPropertyNames();
        $serialized = array_keys($this->serialize(new UnitDataResponse([])));

        foreach ($documented as $property) {
            $this->assertContains($property, $serialized);
        }
    }

    /**
     * The DTO's #[Property] annotation is only half of the contract: the Studio UI never talks to
     * this DTO directly, it talks to the committed OpenAPI snapshot and the client generated from
     * it. Those artifacts are refreshed manually from a running Studio instance and can drift from
     * the annotation without anything failing — which is exactly how the unit select regression
     * happened: the annotation had already been corrected to `unitList`, but the committed snapshot
     * and generated client still carried the old `UnitList` casing, so nothing here caught it.
     */
    public function testCommittedOpenApiSnapshotMatchesTheDocumentedPropertyNames(): void
    {
        $this->assertFileExists(
            self::SNAPSHOT_PATH,
            'Committed OpenAPI snapshot not found at ' . self::SNAPSHOT_PATH
        );

        $snapshot = json_decode(
            file_get_contents(self::SNAPSHOT_PATH),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $schemaProperties = array_keys(
            $snapshot['components']['schemas'][self::SNAPSHOT_SCHEMA_NAME]['properties'] ?? []
        );

        foreach ($this->documentedPropertyNames() as $property) {
            $this->assertContains(
                $property,
                $schemaProperties,
                "The committed OpenAPI snapshot is stale: '{$property}' is documented on " .
                self::SNAPSHOT_SCHEMA_NAME . ' but missing from docs.jsonopenapi.json. Refresh the ' .
                'snapshot from a running Studio instance and regenerate the client.'
            );
        }
    }

    /**
     * Same drift check as above, against the client that actually ships to the browser. The
     * snapshot and the generated client are refreshed together, but asserting on the generated
     * file directly catches a stale/hand-edited client even if the snapshot itself is correct.
     */
    public function testGeneratedClientMatchesTheDocumentedPropertyNames(): void
    {
        $this->assertFileExists(
            self::GENERATED_CLIENT_PATH,
            'Generated API client not found at ' . self::GENERATED_CLIENT_PATH
        );

        $client = file_get_contents(self::GENERATED_CLIENT_PATH);
        $matched = preg_match(
            '/export type ' . self::SNAPSHOT_SCHEMA_NAME . ' = \{([\s\S]*?)\n\};/',
            $client,
            $matches
        );
        $this->assertSame(
            1,
            $matched,
            'Could not find the ' . self::SNAPSHOT_SCHEMA_NAME . ' type in the generated client at ' .
            self::GENERATED_CLIENT_PATH
        );
        $typeBody = $matches[1];

        foreach ($this->documentedPropertyNames() as $property) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($property, '/') . '\??:/',
                $typeBody,
                "The generated API client is stale: '{$property}' is documented on " .
                self::SNAPSHOT_SCHEMA_NAME . ' but missing from the generated ' .
                self::SNAPSHOT_SCHEMA_NAME . ' type. Regenerate the client via `npm run build-api-client`.'
            );
        }
    }
}
