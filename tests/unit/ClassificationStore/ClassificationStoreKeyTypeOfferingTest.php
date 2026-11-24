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
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\ClassificationStoreDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\ClassificationStoreKeyListOptions;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineResolver;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * Which classification store keys the mapping editor's key picker offers for one transformation
 * result type. Every key type below is an allowed classification store key type that had no
 * reachable result type of its own, so the picker could never offer it.
 *
 * Each assertion carries its own control: a key of a type the result type does NOT cover has to
 * stay out of the same listing, so that a listing which simply returns everything cannot pass.
 */
class ClassificationStoreKeyTypeOfferingTest extends Unit
{
    private const STORE = 'diKeyTypeStore';

    private const GROUP = 'diKeyTypeGroup';

    private const CLASS_NAME = 'DiCsKeyTypeClass';

    private const ENGINE_DOCUMENT = 'document';

    private const TYPE_INPUT = 'input';

    private const TYPE_MULTISELECT = 'multiselect';

    private const TYPE_LANGUAGE_MULTISELECT = 'languagemultiselect';

    private const TYPE_NUMERIC_RANGE = 'numericRange';

    private const TYPE_QUANTITY_VALUE_RANGE = 'quantityValueRange';

    private const TYPE_EXTERNAL_IMAGE = 'externalImage';

    private const TYPE_DATE = 'date';

    private const TYPE_QUANTITY_VALUE = 'quantityValue';

    private const TYPE_RELATION = 'manyToManyRelation';

    private const TYPE_OBJECT_RELATION = 'manyToManyObjectRelation';

    private const TYPE_ADVANCED_RELATION = 'advancedManyToManyRelation';

    private const TYPE_ADVANCED_OBJECT_RELATION = 'advancedManyToManyObjectRelation';

    /**
     * The key types a key is created for, under the name the key row carries.
     */
    private const KEY_TYPES = [
        self::TYPE_INPUT,
        self::TYPE_MULTISELECT,
        self::TYPE_LANGUAGE_MULTISELECT,
        self::TYPE_NUMERIC_RANGE,
        self::TYPE_QUANTITY_VALUE_RANGE,
        self::TYPE_EXTERNAL_IMAGE,
        self::TYPE_DATE,
        self::TYPE_QUANTITY_VALUE,
        self::TYPE_RELATION,
        self::TYPE_OBJECT_RELATION,
        self::TYPE_ADVANCED_RELATION,
        self::TYPE_ADVANCED_OBJECT_RELATION,
    ];

    /**
     * @var array<string, int> key type => key id
     */
    private array $keyIds = [];

    private string $classId;

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE, self::ENGINE_DOCUMENT);
        $this->flipEngine($store, self::ENGINE_DOCUMENT);

        $group = $fixture->group($store->getId(), self::GROUP);

        $sorter = 0;
        foreach (self::KEY_TYPES as $type) {
            $key = $fixture->key($store->getId(), self::STORE . '_' . $type, $type);
            $fixture->relate($group->getId(), $key->getId(), ++$sorter);
            $this->keyIds[$type] = $key->getId();
        }

        $this->classId = $fixture->classDefinition(self::CLASS_NAME, $store->getId())->getId();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
    }

    /**
     * `array` is the result type an operator building a list of plain values produces, and the
     * table listed a name under it - `languages` - that no core field type has carried since the
     * type was renamed to `languagemultiselect`.
     */
    public function testALanguageMultiselectKeyIsOfferedForAnArrayResult(): void
    {
        $offered = $this->offeredKeyIds(TransformationDataTypeService::DEFAULT_ARRAY);

        static::assertContains($this->keyIds[self::TYPE_LANGUAGE_MULTISELECT], $offered, 'the renamed type');
        static::assertContains($this->keyIds[self::TYPE_MULTISELECT], $offered, 'a type listed beside it');
        static::assertNotContains($this->keyIds[self::TYPE_INPUT], $offered, 'a type the result cannot fill');
    }

    /**
     * `dateArray`, `quantityValueArray` and `inputQuantityValueArray` are result types a pipeline
     * really produces, and the transformation table names no key type for any of them. The listing
     * used to skip its own filter for an empty list and offer every key in the store, so a pipeline
     * ending in one of them could be pointed at a key of any type at all.
     *
     * The class-attribute listing that reads the same table has always refused in that case, so
     * refusing here is what makes the two consumers of one table agree.
     */
    public function testAResultTypeNoKeyTypeCanFillOffersNoKeyAtAll(): void
    {
        $offered = $this->offeredKeyIds(TransformationDataTypeService::DATE_ARRAY);

        static::assertSame([], $offered, 'a result type with no key type of its own');
        static::assertContains(
            $this->keyIds[self::TYPE_DATE],
            $this->offeredKeyIds(TransformationDataTypeService::DATE),
            'the same listing still offers the key type the sibling result type names'
        );
    }

    public function testANumericRangeKeyIsOfferedForANumericRangeResult(): void
    {
        $offered = $this->offeredKeyIds(TransformationDataTypeService::NUMERIC_RANGE);

        static::assertContains($this->keyIds[self::TYPE_NUMERIC_RANGE], $offered);
        static::assertNotContains($this->keyIds[self::TYPE_INPUT], $offered, 'a type the result cannot fill');
        static::assertNotContains(
            $this->keyIds[self::TYPE_QUANTITY_VALUE_RANGE],
            $offered,
            'the other range type, which carries a unit'
        );
    }

    public function testAQuantityValueRangeKeyIsOfferedForAQuantityValueRangeResult(): void
    {
        $offered = $this->offeredKeyIds(TransformationDataTypeService::QUANTITY_VALUE_RANGE);

        static::assertContains($this->keyIds[self::TYPE_QUANTITY_VALUE_RANGE], $offered);
        static::assertNotContains(
            $this->keyIds[self::TYPE_QUANTITY_VALUE],
            $offered,
            'the single-valued quantity value type'
        );
        static::assertNotContains($this->keyIds[self::TYPE_NUMERIC_RANGE], $offered, 'the range without a unit');
    }

    public function testAnExternalImageKeyIsOfferedForAnExternalImageResult(): void
    {
        $offered = $this->offeredKeyIds(TransformationDataTypeService::EXTERNAL_IMAGE);

        static::assertContains($this->keyIds[self::TYPE_EXTERNAL_IMAGE], $offered);
        static::assertNotContains($this->keyIds[self::TYPE_INPUT], $offered, 'the plain text type a URL arrives as');
    }

    /**
     * An advanced relation key is reachable the way an advanced relation class field is: the caller
     * asks for the advanced relations, and the plain relation result type is read as its advanced
     * counterpart. Without that flag the listing answers exactly as before.
     */
    public function testAnAdvancedRelationKeyIsOfferedOnlyWhenAdvancedRelationsAreAsked(): void
    {
        $plain = $this->offeredKeyIds(TransformationDataTypeService::DATA_OBJECT_ARRAY);
        static::assertNotContains($this->keyIds[self::TYPE_ADVANCED_OBJECT_RELATION], $plain);
        static::assertContains($this->keyIds[self::TYPE_OBJECT_RELATION], $plain, 'the plain relation');

        $advanced = $this->offeredKeyIds(TransformationDataTypeService::DATA_OBJECT_ARRAY, true);
        static::assertContains($this->keyIds[self::TYPE_ADVANCED_OBJECT_RELATION], $advanced);
        static::assertContains($this->keyIds[self::TYPE_OBJECT_RELATION], $advanced, 'still the plain one');
        static::assertNotContains($this->keyIds[self::TYPE_DATE], $advanced, 'a type no relation result covers');
    }

    public function testAnAdvancedAssetRelationKeyIsOfferedOnlyWhenAdvancedRelationsAreAsked(): void
    {
        $plain = $this->offeredKeyIds(TransformationDataTypeService::ASSET_ARRAY);
        static::assertNotContains($this->keyIds[self::TYPE_ADVANCED_RELATION], $plain);
        static::assertContains($this->keyIds[self::TYPE_RELATION], $plain, 'the plain relation');

        $advanced = $this->offeredKeyIds(TransformationDataTypeService::ASSET_ARRAY, true);
        static::assertContains($this->keyIds[self::TYPE_ADVANCED_RELATION], $advanced);
        static::assertNotContains($this->keyIds[self::TYPE_DATE], $advanced, 'a type no relation result covers');
    }

    /**
     * @return int[]
     */
    private function offeredKeyIds(
        string $transformationResultType,
        bool $loadAdvancedRelations = false
    ): array {
        /** @var ClassificationStoreDataTypeService $service */
        $service = $this->tester->grabService(ClassificationStoreDataTypeService::class);
        $list = $service->listClassificationStoreKeyList(
            $this->classId,
            ClassificationStoreFixture::FIELD_NON_LOCALIZED,
            $transformationResultType,
            new ClassificationStoreKeyListOptions(
                limit: 100,
                loadAdvancedRelations: $loadAdvancedRelations
            )
        );

        return array_map(static fn ($relation): int => $relation->getKeyId(), $list->load());
    }

    private function flipEngine(StoreConfig $store, string $engine): void
    {
        $store->setEngine($engine);
        $store->save();

        /** @var EngineResolver $resolver */
        $resolver = $this->tester->grabService(EngineResolver::class);
        $resolver->reset();
    }
}
