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
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\ClassificationStoreFixture;
use Pimcore\Model\DataObject\ClassDefinition\Data\Geopoint;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineResolver;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * The key list the mapping editor offers for a classification store field: which keys it offers and
 * what it publishes for each of them.
 */
class ClassificationStoreKeyListTest extends Unit
{
    private const STORE = 'diListStore';

    private const GROUP = 'diListGroup';

    private const GROUP_IDENTIFIER = 'di-list-group';

    private const KEY_INPUT = 'diListInput';

    private const KEY_INPUT_IDENTIFIER = 'di-list-input';

    private const KEY_GEOPOINT = 'diListGeopoint';

    private const KEY_MULTI_INPUT = 'diListMultiInput';

    private const KEY_MULTI_GEOPOINT = 'diListMultiGeopoint';

    private const CLASS_NAME = 'DiCsListClass';

    private const ENGINE_DOCUMENT = 'document';

    private const ENGINE_EAV = 'eav';

    private const TYPE_MULTI_VALUE = 'multiValue';

    private int $groupId;

    private int $inputKeyId;

    private int $geopointKeyId;

    private int $multiInputKeyId;

    private int $multiGeopointKeyId;

    private string $classId;

    protected function _before(): void
    {
        $fixture = new ClassificationStoreFixture();
        $store = $fixture->store(self::STORE, self::ENGINE_DOCUMENT);
        $this->flipEngine($store, self::ENGINE_DOCUMENT);

        $group = $fixture->group($store->getId(), self::GROUP, self::GROUP_IDENTIFIER);
        $input = $fixture->key($store->getId(), self::KEY_INPUT, 'input', null, self::KEY_INPUT_IDENTIFIER);
        $geopoint = $fixture->key($store->getId(), self::KEY_GEOPOINT, 'geopoint', new Geopoint());
        $multiInput = $fixture->key(
            $store->getId(),
            self::KEY_MULTI_INPUT,
            self::TYPE_MULTI_VALUE,
            $this->multiValueOver('input', self::KEY_MULTI_INPUT)
        );
        $multiGeopoint = $fixture->key(
            $store->getId(),
            self::KEY_MULTI_GEOPOINT,
            self::TYPE_MULTI_VALUE,
            $this->multiValueOver('geopoint', self::KEY_MULTI_GEOPOINT)
        );
        $fixture->relate($group->getId(), $input->getId(), 1);
        $fixture->relate($group->getId(), $geopoint->getId(), 2);
        $fixture->relate($group->getId(), $multiInput->getId(), 3);
        $fixture->relate($group->getId(), $multiGeopoint->getId(), 4);

        $this->groupId = $group->getId();
        $this->inputKeyId = $input->getId();
        $this->geopointKeyId = $geopoint->getId();
        $this->multiInputKeyId = $multiInput->getId();
        $this->multiGeopointKeyId = $multiGeopoint->getId();
        $this->classId = $fixture->classDefinition(self::CLASS_NAME, $store->getId())->getId();
    }

    protected function _after(): void
    {
        $this->flipEngine((new ClassificationStoreFixture())->store(self::STORE), self::ENGINE_DOCUMENT);
        TestHelper::cleanUp();
    }

    public function testAKeyTheEnginesCannotHoldIsNotOffered(): void
    {
        $this->flipEngine((new ClassificationStoreFixture())->store(self::STORE), self::ENGINE_EAV);

        $offered = $this->offeredKeyIds();

        static::assertContains($this->inputKeyId, $offered);
        static::assertNotContains($this->geopointKeyId, $offered);
    }

    /**
     * What a `multiValue` key can be held in is what its delegate can be held in, so the two of
     * them part company on an engine that holds no structured values - although both key rows
     * carry the same `multiValue` in their type column.
     */
    public function testAMultiValueKeyIsOfferedByWhatItsDelegateCanBeHeldIn(): void
    {
        $this->flipEngine((new ClassificationStoreFixture())->store(self::STORE), self::ENGINE_EAV);

        $offered = $this->offeredKeyIds();

        static::assertContains($this->multiInputKeyId, $offered);
        static::assertNotContains($this->multiGeopointKeyId, $offered);
    }

    public function testEveryKeyIsOfferedWhereTheEngineHoldsStructuredValues(): void
    {
        $offered = $this->offeredKeyIds();

        static::assertContains($this->inputKeyId, $offered);
        static::assertContains($this->geopointKeyId, $offered);
        static::assertContains($this->multiInputKeyId, $offered);
        static::assertContains($this->multiGeopointKeyId, $offered);
    }

    /**
     * The picker the mapping editor opens always sends a `transformationResultType`, and the keys
     * it offers for one are filtered on their type column. A `multiValue` key's type column is
     * literally `multiValue`, which is no transformation target type's field type, so it is
     * offered by the type its delegate carries - the type whose value it actually holds.
     */
    public function testAMultiValueKeyIsOfferedForTheTransformationTypeOfItsDelegate(): void
    {
        $offered = $this->offeredKeyIds(TransformationDataTypeService::DEFAULT_TYPE);

        static::assertContains($this->inputKeyId, $offered, 'the delegate type itself');
        static::assertContains($this->multiInputKeyId, $offered, 'the key wrapping it');
        static::assertNotContains($this->geopointKeyId, $offered, 'an unrelated type');
        static::assertNotContains($this->multiGeopointKeyId, $offered, 'a key wrapping an unrelated type');
    }

    public function testTheKeysListingPublishesTheIdentifierReferenceAlongsideTheIntegerPair(): void
    {
        /** @var DataTypeServiceInterface $service */
        $service = $this->tester->grabService(DataTypeServiceInterface::class);

        // the type the picker sends for a plain text column, rather than no filter at all: the
        // endpoint is never reached without one
        $response = $service->loadClassificationStoreKeys(new ClassificationStoreKeyParameters(
            classId: $this->classId,
            fieldName: ClassificationStoreFixture::FIELD_NON_LOCALIZED,
            transformationResultType: TransformationDataTypeService::DEFAULT_TYPE,
            limit: 50
        ));

        $rows = array_column($response->getData(), null, 'keyId');
        static::assertSame($this->groupId . '-' . $this->inputKeyId, $rows[$this->inputKeyId]['id']);
        static::assertSame(
            self::GROUP_IDENTIFIER . ':' . self::KEY_INPUT_IDENTIFIER,
            $rows[$this->inputKeyId]['identifier']
        );
    }

    /**
     * @param string $transformationResultType the picker's own filter, empty for no filter at all
     *
     * @return int[]
     */
    private function offeredKeyIds(string $transformationResultType = ''): array
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

    private function multiValueOver(string $delegateType, string $name): MultiValue
    {
        $definition = new MultiValue();
        $definition->setName($name);
        $definition->setDelegateDatatype($delegateType);
        $definition->setMultiValue(true);

        return $definition;
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
