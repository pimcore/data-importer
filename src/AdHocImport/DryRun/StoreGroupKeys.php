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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\Service;

/**
 * @internal
 *
 * @phpstan-import-type GroupKey from StoreGroupCheck
 */
final class StoreGroupKeys
{
    /**
     * The keys of a group as Pimcore's check of a save sees them: with the group's mandatory mark.
     *
     * @return list<GroupKey>
     */
    public static function of(int $groupId): array
    {
        $group = GroupConfig::getById($groupId);
        if ($group === null) {
            return [];
        }

        $keys = [];
        foreach ($group->getRelations() as $relation) {
            $definition = Service::getFieldDefinitionFromJson(
                (array) json_decode($relation->getDefinition(), true),
                $relation->getType()
            );
            // unreachable through getRelations(), whose listing always adds 'mandatory'; kept for the nullable return
            if ($definition === null) {
                continue;
            }
            if ($relation->isMandatory()) {
                $definition->setMandatory(true);
            }
            $keys[] = [
                'key' => new StoreKey($groupId, $relation->getKeyId()),
                'label' => $group->getName() . ' › ' . $relation->getName(),
                'definition' => $definition,
            ];
        }

        return $keys;
    }
}
