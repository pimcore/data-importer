<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Mapping\Type;

use Pimcore\Model\DataObject;

class ClassificationStoreDataTypeService
{
    /**
     * @var TransformationDataTypeService
     */
    protected $transformationDataTypeService;

    /**
     * @param TransformationDataTypeService $transformationDataTypeService
     */
    public function __construct(TransformationDataTypeService $transformationDataTypeService)
    {
        $this->transformationDataTypeService = $transformationDataTypeService;
    }

    public function listClassificationStoreKeyList(string $classId, string $fieldName, string $transformationResultType, string $orderKey = 'name', string $order = 'ASC', int $start = 0, int $limit = 15, string $searchString = null, string $filterString = null): DataObject\Classificationstore\KeyGroupRelation\Listing
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
//                $conditionParts[] = '';
                $list->addConditionParam(sprintf('type IN (%s)', "'" . implode("','", $pimcoreTypes) . "'"));
//                $list->addConditionParam('type IN (?)', $pimcoreTypes);
            }
        }

        $condition = implode(' AND ', $conditionParts);
        $list->setCondition($condition);
        $list->setResolveGroupName(true);

        return $list;
    }
}
