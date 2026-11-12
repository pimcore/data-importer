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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ClassAttributesEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ClassificationStoreKeyNameEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ClassificationStoreKeysEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\UnitDataEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\DataTypeHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\DataTarget\ClassificationstoreKeyReference;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\ClassificationStoreDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyNameResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\UnitDataResponse;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class DataTypeService implements DataTypeServiceInterface
{
    public function __construct(
        private TransformationDataTypeService $transformationDataTypeService,
        private ClassificationStoreDataTypeService $classificationStoreDataTypeService,
        private DataTypeHydratorInterface $dataTypeHydrator,
        private EventDispatcherInterface $eventDispatcher,
        private LocalizationResolver $localizationResolver
    ) {
    }

    public function loadClassAttributes(
        string $classId,
        string|array $transformationResultType,
        bool $includeSystemRead,
        bool $includeSystemWrite,
        bool $loadAdvancedRelations
    ): ClassAttributesResponse {
        $attributes = $this->transformationDataTypeService->getPimcoreDataTypes(
            $classId,
            $transformationResultType,
            $includeSystemRead,
            $includeSystemWrite,
            $loadAdvancedRelations
        );

        $response = $this->dataTypeHydrator->hydrateClassAttributes($attributes);

        $this->eventDispatcher->dispatch(
            new ClassAttributesEvent($response),
            ClassAttributesEvent::EVENT_NAME
        );

        return $response;
    }

    public function loadClassificationStoreAttributes(string $classId): ClassAttributesResponse
    {
        $attributes = $this->transformationDataTypeService->getClassificationStoreAttributes($classId);

        $response = $this->dataTypeHydrator->hydrateClassAttributes($attributes);

        $this->eventDispatcher->dispatch(
            new ClassAttributesEvent($response),
            ClassAttributesEvent::EVENT_NAME
        );

        return $response;
    }

    public function loadClassificationStoreKeys(
        ClassificationStoreKeyParameters $parameters
    ): ClassificationStoreKeysResponse {
        $sortParams = $this->extractSortingSettings($parameters->getSort());

        $list = $this->classificationStoreDataTypeService->listClassificationStoreKeyList(
            strip_tags($parameters->getClassId() ?? ''),
            strip_tags($parameters->getFieldName() ?? ''),
            strip_tags($parameters->getTransformationResultType() ?? ''),
            $sortParams['orderKey'] ?? 'name',
            $sortParams['order'] ?? 'ASC',
            $parameters->getStart(),
            $parameters->getLimit(),
            $parameters->getSearchfilter() !== null ? strip_tags($parameters->getSearchfilter()) : null,
            $parameters->getFilter() !== null ? strip_tags($parameters->getFilter()) : null
        );

        DataObject\Classificationstore\DefinitionCache::load(array_map(
            static fn ($relation): int => $relation->getKeyId(),
            $list->load()
        ));

        $data = [];
        foreach ($list as $config) {
            $item = [
                'keyId' => $config->getKeyId(),
                'groupId' => $config->getGroupId(),
                'keyName' => $config->getName(),
                'keyDescription' => $config->getDescription(),
                'id' => $config->getGroupId() . '-' . $config->getKeyId(),
                'identifier' => null,
                'sorter' => $config->getSorter(),
            ];

            $groupConfig = DataObject\Classificationstore\GroupConfig::getById($config->getGroupId());
            if ($groupConfig) {
                $item['groupName'] = $groupConfig->getName();
            }

            $keyConfig = DataObject\Classificationstore\DefinitionCache::get($config->getKeyId());
            $groupIdentifier = $groupConfig?->getIdentifier();
            $keyIdentifier = $keyConfig?->getIdentifier();
            if ($groupIdentifier !== null && $keyIdentifier !== null) {
                $item['identifier'] = $groupIdentifier . ':' . $keyIdentifier;
            }

            $data[] = $item;
        }

        $response = $this->dataTypeHydrator->hydrateClassificationStoreKeys($data, $list->getTotalCount());

        $this->eventDispatcher->dispatch(
            new ClassificationStoreKeysEvent($response),
            ClassificationStoreKeysEvent::EVENT_NAME
        );

        return $response;
    }

    public function loadClassificationStoreKeyName(
        string $keyId,
        ?string $classId = null,
        ?string $fieldName = null
    ): ClassificationStoreKeyNameResponse {
        $keyGroupRelation = $this->findKeyGroupRelation($keyId, $classId, $fieldName);
        $group = $keyGroupRelation
            ? DataObject\Classificationstore\GroupConfig::getById($keyGroupRelation->getGroupId())
            : null;

        if ($keyGroupRelation && $group) {
            $definition = DataObject\Classificationstore\Service::getFieldDefinitionFromKeyConfig($keyGroupRelation);

            $response = $this->dataTypeHydrator->hydrateClassificationStoreKeyName(
                groupName: $group->getName(),
                keyName: $keyGroupRelation->getName(),
                variants: $definition instanceof MultiValue ? $definition->getVariantNames() : [],
                localized: $this->isKeyLocalized($keyGroupRelation->getKeyId(), $classId, $fieldName)
            );

            $this->eventDispatcher->dispatch(
                new ClassificationStoreKeyNameEvent($response),
                ClassificationStoreKeyNameEvent::EVENT_NAME
            );

            return $response;
        }

        $response = $this->dataTypeHydrator->hydrateClassificationStoreKeyName($keyId);

        $this->eventDispatcher->dispatch(
            new ClassificationStoreKeyNameEvent($response),
            ClassificationStoreKeyNameEvent::EVENT_NAME
        );

        return $response;
    }

    private function isKeyLocalized(int $keyId, ?string $classId, ?string $fieldName): ?bool
    {
        $field = $classId !== null && $fieldName !== null
            ? DataObject\ClassDefinition::getById($classId)?->getFieldDefinition($fieldName)
            : null;

        return $field instanceof DataObject\ClassDefinition\Data\Classificationstore
            ? $this->localizationResolver->isKeyLocalized($keyId, $field)
            : null;
    }

    private function findKeyGroupRelation(
        string $keyId,
        ?string $classId,
        ?string $fieldName
    ): ?DataObject\Classificationstore\KeyGroupRelation {
        $reference = ClassificationstoreKeyReference::parse($keyId);
        if (!$reference instanceof ClassificationstoreKeyReference) {
            return null;
        }

        if (!$reference->isIdentifier) {
            return DataObject\Classificationstore\KeyGroupRelation::getByGroupAndKeyId(
                (int) $reference->group,
                (int) $reference->key
            );
        }

        $field = $classId !== null && $fieldName !== null
            ? DataObject\ClassDefinition::getById($classId)?->getFieldDefinition($fieldName)
            : null;
        if (!$field instanceof DataObject\ClassDefinition\Data\Classificationstore) {
            return null;
        }

        $storeId = (int) $field->getStoreId();
        $group = DataObject\Classificationstore\GroupConfig::getByIdentifier($reference->group, $storeId);
        $key = DataObject\Classificationstore\KeyConfig::getByIdentifier($reference->key, $storeId);

        return $group && $key
            ? DataObject\Classificationstore\KeyGroupRelation::getByGroupAndKeyId($group->getId(), $key->getId())
            : null;
    }

    public function loadUnitData(): UnitDataResponse
    {
        $unitList = new DataObject\QuantityValue\Unit\Listing();
        $unitList->setOrderKey('abbreviation');

        $data = [];
        foreach ($unitList as $unit) {
            $data[] = [
                'unitId' => $unit->getId(),
                'abbreviation' => $unit->getAbbreviation(),
            ];
        }

        $response = $this->dataTypeHydrator->hydrateUnitData($data);

        $this->eventDispatcher->dispatch(
            new UnitDataEvent($response),
            UnitDataEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * Extract sorting settings from a JSON-encoded sort parameter.
     * Replicates the behavior of Pimcore\Bundle\AdminBundle\Helper\QueryParams::extractSortingSettings.
     */
    public function extractSortingSettings(?string $sort): array
    {
        if ($sort === null) {
            return [];
        }

        $params = json_decode($sort, true);
        if (!is_array($params)) {
            return [];
        }

        // Handle ExtJS grid sort format: [{"property":"name","direction":"ASC"}]
        if (isset($params[0])) {
            return [
                'orderKey' => $params[0]['property'] ?? null,
                'order' => $params[0]['direction'] ?? null,
            ];
        }

        return [];
    }
}
