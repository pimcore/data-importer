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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\ClassificationStore;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreKeyReference;

/**
 * How a group and key reference of a mapping entry is read, in both spellings.
 */
class ClassificationstoreKeyReferenceTest extends Unit
{
    public function testAnIdentifierPairIsSplitOnTheColon(): void
    {
        $reference = ClassificationstoreKeyReference::parse('my-group:my-key');

        static::assertInstanceOf(ClassificationstoreKeyReference::class, $reference);
        static::assertTrue($reference->isIdentifier);
        static::assertSame('my-group', $reference->group);
        static::assertSame('my-key', $reference->key);
        static::assertNull($reference->variant);
    }

    public function testAnIntegerPairIsStillReadAsToday(): void
    {
        $reference = ClassificationstoreKeyReference::parse('12-34');

        static::assertInstanceOf(ClassificationstoreKeyReference::class, $reference);
        static::assertFalse($reference->isIdentifier);
        static::assertSame('12', $reference->group);
        static::assertSame('34', $reference->key);
    }

    public function testAVariantFollowsTheHash(): void
    {
        $identifier = ClassificationstoreKeyReference::parse('grp:key#metric');
        $integer = ClassificationstoreKeyReference::parse('1-2#imperial');

        static::assertSame('metric', $identifier?->variant);
        static::assertSame('grp:key', $identifier?->reference);
        static::assertSame('imperial', $integer?->variant);
        static::assertSame('1-2', $integer?->reference);
    }

    /**
     * @dataProvider malformedTokens
     */
    public function testAMalformedTokenIsNotAReference(string $token): void
    {
        static::assertNull(ClassificationstoreKeyReference::parse($token));
    }

    /**
     * @return array<string, array{string}>
     */
    public function malformedTokens(): array
    {
        return [
            'nothing' => [''],
            'one half' => ['12'],
            'empty group' => [':key'],
            'empty key' => ['grp:'],
            'three identifiers' => ['a:b:c'],
            'empty variant' => ['grp:key#'],
            // not two halves: reading the first two addresses group 1 key 2, which the token
            // never named - and neither `Classificationstore::setSettings()` nor the keys
            // listing re-checks the count
            'three integers' => ['1-2-3'],
            'three integers with a variant' => ['1-2-3#metric'],
            'four integers' => ['1-2-3-4'],
            // two halves, but neither is an integer: both are cast to int by whoever reads
            // them, which addresses group 0 key 0
            'a hyphen pair that is not integers' => ['abc-def'],
            'a half that is not an integer' => ['1-def'],
        ];
    }
}
