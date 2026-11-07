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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Response\ElementIcon;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\ElementIconTypes;

/**
 * The class list Studio's class definition service answers for the classes a user may create.
 */
final class AdHocCreatableClasses
{
    /**
     * @param string[] $ids
     *
     * @return list<ClassDefinitionList>
     */
    public static function listed(array $ids, bool $creatableOnly): array
    {
        if (!$creatableOnly) {
            return [];
        }

        return array_values(array_map(
            static fn (string $id): ClassDefinitionList => new ClassDefinitionList(
                $id,
                $id,
                $id,
                new ElementIcon(ElementIconTypes::NAME->value, 'class')
            ),
            $ids
        ));
    }
}
