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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits;

use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;

/**
 * Requires the using class to have a property: ClassDefinitionServiceInterface $classDefinitionService
 *
 * @internal
 *
 * @property ClassDefinitionServiceInterface $classDefinitionService
 */
trait CreatableClassTrait
{
    /**
     * @throws ForbiddenException if the user may not create objects of that class
     */
    private function assertMayCreate(string $classId): void
    {
        $creatable = array_map(
            static fn (ClassDefinitionList $class): string => $class->getId(),
            $this->classDefinitionService->getClassDefinitionCollection(creatableOnly: true)
        );
        if (!in_array($classId, $creatable, true)) {
            throw new ForbiddenException(sprintf('Access denied to class %s', $classId));
        }
    }
}
