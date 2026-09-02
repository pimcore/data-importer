<?php declare(strict_types=1);

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use OpenApi\Attributes\Property;
use OpenApi\Context;
use OpenApi\Generator;
use Pimcore\Bundle\DataImporterBundle\Schema\UnitDataResponse;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class UnitDataResponseTest extends Unit
{
    protected $tester;

    private function serialize(UnitDataResponse $response): array
    {
        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        return json_decode($serializer->serialize($response, 'json'), true, 512, JSON_THROW_ON_ERROR);
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
        $constructor = (new ReflectionClass(UnitDataResponse::class))->getConstructor();
        $this->assertNotNull($constructor);

        // ReflectionAttribute::getArguments() evaluates any `new` expression used as an
        // argument value (e.g. the nested `items: new Items(...)` on the unitList property),
        // which constructs real swagger-php annotation objects. Their constructor reads the
        // static Generator::$context; on swagger-php < 5.0.2 (resolved by the "lowest"
        // dependency CI matrix) that property has no default and is genuinely uninitialized
        // outside of a full Generator::scan() run, so accessing it throws. Initialize it to a
        // standalone context so reflecting the attribute in isolation doesn't fail.
        $contextProperty = new ReflectionProperty(Generator::class, 'context');
        if (!$contextProperty->isInitialized()) {
            Generator::$context = new Context();
        }

        $documented = [];
        foreach ($constructor->getParameters() as $parameter) {
            foreach ($parameter->getAttributes(Property::class) as $attribute) {
                $documented[] = $attribute->getArguments()['property'] ?? $parameter->getName();
            }
        }

        $serialized = array_keys($this->serialize(new UnitDataResponse([])));

        foreach ($documented as $property) {
            $this->assertContains($property, $serialized);
        }
    }
}
