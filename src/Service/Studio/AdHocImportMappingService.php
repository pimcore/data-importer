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

use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportMappingResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CreatableClassTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;

/**
 * @internal
 */
final readonly class AdHocImportMappingService implements AdHocImportMappingServiceInterface
{
    use CreatableClassTrait;

    // An ad hoc import keeps objects where it finds them, names new ones by their match value and cannot set the
    // object type of a class with variants
    private const array NO_MAPPING_TARGETS = ['key', 'path', 'type'];

    public function __construct(
        private ClassDefinitionServiceInterface $classDefinitionService, // NOSONAR php:S1068 read by CreatableClassTrait
        private MappingSuggestionServiceInterface $mappingSuggestionService,
        private ClassDefinitionResolverInterface $classDefinitionResolver,
    ) {
    }

    public function loadMapping(MappingSuggestionParameters $parameters): AdHocImportMappingResponse
    {
        $classId = $parameters->getClassId();
        $this->assertMayCreate($classId);

        $class = $this->classDefinitionResolver->getById($classId);
        $fields = [];
        foreach ($this->mappingSuggestionService->loadTargets($classId) as $target) {
            if (!in_array($target['key'], self::NO_MAPPING_TARGETS, true)) {
                $fields[] = [...$target, 'mandatory' => $this->isMandatory($class, $target['key'])];
            }
        }

        $suggestions = array_values(array_filter(
            $this->mappingSuggestionService->suggest($parameters)->getSuggestions(),
            static fn (array $suggestion): bool => !in_array(
                $suggestion['targetFieldName'],
                self::NO_MAPPING_TARGETS,
                true
            )
        ));

        return new AdHocImportMappingResponse($fields, $suggestions);
    }

    // a localized field is found among the class's localized fields
    private function isMandatory(?ClassDefinition $class, string $key): bool
    {
        return $class?->getFieldDefinition($key)?->getMandatory() ?? false;
    }
}
