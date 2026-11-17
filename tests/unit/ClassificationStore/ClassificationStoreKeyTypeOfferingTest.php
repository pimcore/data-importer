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

    /**
     * The key types a key is created for, under the name the key row carries.
     */
    private const KEY_TYPES = [
        'input',
        'multiselect',
        'languagemultiselect',
        'numericRange',
        'quantityValueRange',
        'externalImage',
        'date',
        'quantityValue',
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

        static::assertContains($this->keyIds['languagemultiselect'], $offered, 'the renamed type');
        static::assertContains($this->keyIds['multiselect'], $offered, 'a type listed beside it');
        static::assertNotContains($this->keyIds['input'], $offered, 'a type the result cannot fill');
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
            $this->keyIds['date'],
            $this->offeredKeyIds(TransformationDataTypeService::DATE),
            'the same listing still offers the key type the sibling result type names'
        );
    }

    /**
     * @return int[]
     */
    private function offeredKeyIds(string $transformationResultType): array
    {
        /** @var ClassificationStoreDataTypeService $service */
        $service = $this->tester->grabService(ClassificationStoreDataTypeService::class);
        $list = $service->listClassificationStoreKeyList(
            $this->classId,
            ClassificationStoreFixture::FIELD_NON_LOCALIZED,
            $transformationResultType,
            limit: 100
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
