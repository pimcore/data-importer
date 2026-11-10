<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Type;

use Doctrine\DBAL\Connection;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineFeature;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineResolver;
use Pimcore\Model\DataObject\Classificationstore\KeyTypeSupport;

/**
 * @internal
 */
final class ClassificationStoreDataTypeService
{
    /**
     * The type column of a wrapper key names the wrapper, not the value. A `multiValue` key's column
     * is literally `multiValue` - {@see DataObject\ClassDefinition\Data\MultiValue::getFieldType()} -
     * and what its value IS, and therefore which transformation target type can produce it, is its
     * delegate's. So both filters below read the delegate for such a key instead of its own type.
     */
    private const TYPE_MULTI_VALUE = 'multiValue';

    /**
     * The `delegateDatatype` of the key row the listing joins, out of its `definition` document.
     * `NULL` for a key that declares no delegate and for every non-wrapper type.
     */
    private const DELEGATE_TYPE = 'JSON_UNQUOTE(JSON_EXTRACT('
        . DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS . '.`definition`, \'$.delegateDatatype\'))';

    public function __construct(
        private readonly TransformationDataTypeService $transformationDataTypeService,
        private readonly KeyTypeSupport $keyTypeSupport,
        private readonly EngineResolver $engineResolver,
        private readonly Connection $connection
    ) {
    }

    public function listClassificationStoreKeyList(string $classId, string $fieldName, string $transformationResultType, string $orderKey = 'name', string $order = 'ASC', int $start = 0, int $limit = 15, ?string $searchString = null, ?string $filterString = null): DataObject\Classificationstore\KeyGroupRelation\Listing
    {
        $classDefinition = DataObject\ClassDefinition::getById($classId);
        $field = $classDefinition->getFieldDefinition($fieldName);
        if ($field instanceof DataObject\ClassDefinition\Data\Classificationstore) {
            $storeId = $field->getStoreId();
        } else {
            throw new \Exception("Invalid field, `$fieldName` is not a classification store");
        }

        $mapping = [
            'groupName' => DataObject\Classificationstore\GroupConfig\Dao::TABLE_NAME_GROUPS .'.name',
            'keyName' => DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS .'.name',
            'keyDescription' => DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS. '.description'
        ];

        if ($orderKey == 'keyName') {
            $orderKey = 'name';
        }

        $list = new DataObject\Classificationstore\KeyGroupRelation\Listing();
        $list->setLimit($limit);
        $list->setOffset($start);
        $list->setOrder($order);
        $list->setOrderKey($orderKey);

        $conditionParts = [];

        if ($filterString) {
            $filters = json_decode($filterString);
            $count = 0;
            foreach ($filters as $f) {
                $count++;
                $fieldname = $mapping[$f->property];
                $conditionParts[] = $fieldname . ' LIKE ' . $list->quote('%' . $f->value . '%');
            }
        }

        $conditionParts[] = '  groupId IN (select id from classificationstore_groups where storeId = ' . $list->quote($storeId) . ')';

        if ($searchString) {
            $conditionParts[] = '('
                . DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS . '.name LIKE ' . $list->quote('%' . $searchString . '%')
                . ' OR ' . DataObject\Classificationstore\GroupConfig\Dao::TABLE_NAME_GROUPS . '.name LIKE ' . $list->quote('%' . $searchString . '%')
                . ' OR ' . DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS . '.description LIKE ' . $list->quote('%' . $searchString . '%') . ')';
        }

        if ($transformationResultType) {
            $pimcoreTypes = $this->transformationDataTypeService->getPimcoreTypesByTransformationTargetType($transformationResultType);
            if (!empty($pimcoreTypes)) {
                $quoted = implode(',', array_map($list->quote(...), $pimcoreTypes));
                $list->addConditionParam(sprintf(
                    '(type IN (%1$s) OR (type = %2$s AND %3$s IN (%1$s)))',
                    $quoted,
                    $list->quote(self::TYPE_MULTI_VALUE),
                    self::DELEGATE_TYPE
                ));
            }
        }

        $unholdable = $this->listUnholdableKeyTypes((int) $storeId);
        if ($unholdable !== []) {
            $conditionParts[] = 'NOT (' . implode(' OR ', $unholdable) . ')';
        }

        $condition = implode(' AND ', $conditionParts);
        $list->setCondition($condition);
        $list->setResolveGroupName(true);

        return $list;
    }

    /**
     * One SQL predicate per key shape of the store the engine behind it cannot hold, asked of core
     * rather than listed here. A shape is a type and the delegate it wraps, because that is what
     * core is asked: `multiValue` is held exactly when its delegate is, so the type alone would
     * answer "held" for a `multiValue` key over a `geopoint` on an EAV store.
     *
     * @return string[] predicates to be refused, to be joined with OR
     */
    private function listUnholdableKeyTypes(int $storeId): array
    {
        if ($this->engineResolver->resolveForStore($storeId)->supports(EngineFeature::StructuredValues)) {
            return [];
        }

        $shapes = $this->connection->fetchAllNumeric(
            'SELECT DISTINCT `type`, ' . self::DELEGATE_TYPE . ' FROM '
            . DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS . ' WHERE `storeId` = ?',
            [$storeId]
        );

        $predicates = [];
        foreach ($shapes as [$type, $delegateType]) {
            $type = (string) $type;
            $delegateType = $delegateType === null ? null : (string) $delegateType;
            if ($this->keyTypeSupport->isHeldInValueColumns($type, $delegateType)) {
                continue;
            }

            // null-safe, so that a key carrying no delegate is refused by the same predicate shape
            $predicates[] = sprintf(
                '(type = %s AND %s <=> %s)',
                $this->connection->quote($type),
                self::DELEGATE_TYPE,
                $delegateType === null ? 'NULL' : $this->connection->quote($delegateType)
            );
        }

        return $predicates;
    }
}
