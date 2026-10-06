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

namespace Pimcore\Bundle\DataImporterBundle\Tests;

use Codeception\Test\Unit;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceLoadStrategy;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceLookupInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceQuery;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\ReferenceType;
use Pimcore\Bundle\DataImporterBundle\Mapping\Operator\Simple\LoadDataObject;
use Pimcore\Bundle\DataImporterBundle\Tool\DataObjectLoader;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;

/**
 * What the `attribute` load strategy of `loadDataObject` hands to a reference lookup.
 */
class AttributeReferenceLookupTest extends Unit
{
    private const KEY = ' 00123 ';

    private const SETTINGS = [
        'loadStrategy' => 'attribute',
        'attributeDataObjectClassId' => 'product',
        'attributeName' => 'sku',
        'attributeLanguage' => 'de',
        'loadUnpublished' => true,
    ];

    public function testExactMatchPassesTheAttributeSettingsAndTheKeyUnchanged(): void
    {
        [$found, $queries] = $this->processWithLookup(self::SETTINGS + ['partialMatch' => false]);

        $this->assertInstanceOf(DataObject::class, $found);
        $this->assertEquals([
            new ReferenceQuery(
                ReferenceType::DataObject,
                ReferenceLoadStrategy::Attribute,
                self::KEY,
                classId: 'product',
                attributeName: 'sku',
                attributeLanguage: 'de',
                partialMatch: false,
                includeUnpublished: true,
            ),
        ], $queries);
        $this->assertStringNotContainsString('%', $queries[0]->key);
    }

    public function testPartialMatchPassesTheKeyWithoutWildcards(): void
    {
        [, $queries] = $this->processWithLookup(self::SETTINGS + ['partialMatch' => true]);

        $this->assertCount(1, $queries);
        $this->assertTrue($queries[0]->partialMatch);
        $this->assertSame(self::KEY, $queries[0]->key);
    }

    public function testDefaultsAreAnExactMatchOfPublishedObjects(): void
    {
        [, $queries] = $this->processWithLookup([
            'loadStrategy' => 'attribute',
            'attributeDataObjectClassId' => 'product',
            'attributeName' => 'sku',
        ]);

        $this->assertFalse($queries[0]->partialMatch);
        $this->assertFalse($queries[0]->includeUnpublished);
        $this->assertSame('', $queries[0]->attributeLanguage);
    }

    public function testNoLookupWithoutAnAttributeName(): void
    {
        $queries = new \ArrayObject();
        $operator = $this->operator(['loadStrategy' => 'attribute']);
        $scope = new MappingApplicationScope();
        $operator->setMappingApplicationScope($scope);
        $result = $scope->run($this->lookup($queries), fn () => $operator->process(self::KEY));

        $this->assertNull($result);
        $this->assertCount(0, $queries);
    }

    /**
     * @return array{0: mixed, 1: list<ReferenceQuery>}
     */
    private function processWithLookup(array $settings): array
    {
        $queries = new \ArrayObject();
        $operator = $this->operator($settings);
        $scope = new MappingApplicationScope();
        $operator->setMappingApplicationScope($scope);

        // the found element skips the operator's own lookup, which needs the database
        $found = $scope->run($this->lookup($queries), fn () => $operator->process(self::KEY));

        return [$found, $queries->getArrayCopy()];
    }

    private function operator(array $settings): LoadDataObject
    {
        $operator = new LoadDataObject($this->createMock(ApplicationLogger::class));
        $operator->setDataObjectLoader(new DataObjectLoader());
        $operator->setSettings($settings);

        return $operator;
    }

    private function lookup(\ArrayObject $queries): ReferenceLookupInterface
    {
        return new class($queries) implements ReferenceLookupInterface {
            public function __construct(private readonly \ArrayObject $queries)
            {
            }

            public function find(ReferenceQuery $query): ?ElementInterface
            {
                $this->queries[] = $query;

                return new DataObject\Folder();
            }
        };
    }
}
