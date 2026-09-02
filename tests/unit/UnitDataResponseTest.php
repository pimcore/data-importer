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

        $documented = [];
        foreach ($constructor->getParameters() as $parameter) {
            foreach ($parameter->getAttributes(Property::class) as $attribute) {
                $property = $attribute->newInstance()->property;
                $documented[] = Generator::isDefault($property) ? $parameter->getName() : $property;
            }
        }

        $serialized = array_keys($this->serialize(new UnitDataResponse([])));

        foreach ($documented as $property) {
            $this->assertContains($property, $serialized);
        }
    }
}
