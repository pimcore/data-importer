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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\ClassLayoutGroups;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportMappingResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeyParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassificationStoreKeysResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CreatableClassTrait;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\Objectbrick\DefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;

/**
 * @internal
 */
final readonly class AdHocImportMappingService implements AdHocImportMappingServiceInterface
{
    use CreatableClassTrait;

    // An ad hoc import keeps objects where it finds them, names new ones by their match value and cannot set the
    // object type of a class with variants
    public const array NO_MAPPING_TARGETS = ['key', 'path', 'type'];

    public function __construct(
        private ClassDefinitionServiceInterface $classDefinitionService, // NOSONAR php:S1068 read by CreatableClassTrait
        private MappingSuggestionServiceInterface $mappingSuggestionService,
        private ClassDefinitionResolverInterface $classDefinitionResolver,
        private DataTypeServiceInterface $dataTypeService,
        private DefinitionResolverInterface $brickDefinitionResolver,
        private TransformationChain $transformationChain,
    ) {
    }

    public function loadMapping(MappingSuggestionParameters $parameters): AdHocImportMappingResponse
    {
        $classId = $parameters->getClassId();
        $this->assertMayCreate($classId);

        $class = $this->classDefinitionResolver->getById($classId);
        $groups = $class === null ? null : ClassLayoutGroups::of($class);
        $accepted = $this->transformationChain->acceptedTypes($classId);
        $fields = [];
        foreach ($this->mappingSuggestionService->loadTargets($classId) as $target) {
            if (!in_array($target['key'], self::NO_MAPPING_TARGETS, true)) {
                $fields[] = [
                    ...$target,
                    'mandatory' => $this->isMandatory($class, $target['key']),
                    'takesColumnAsIs' => $this->transformationChain->takesColumnAsIs($accepted[$target['key']] ?? []),
                    'group' => $groups?->groupOf($target['key']) ?? [],
                    'classificationStore' => false,
                ];
            }
        }
        foreach ($class?->getFieldDefinitions() ?? [] as $definition) {
            if ($definition instanceof Classificationstore) {
                $key = (string) $definition->getName();
                $fields[] = [
                    'key' => $key,
                    'title' => $definition->getTitle() . ' [' . $key . ']',
                    'localized' => (bool) $definition->isLocalized(),
                    // a key of the store is written by the type of that key
                    'transformationResultType' => TransformationDataTypeService::DEFAULT_TYPE,
                    'mandatory' => (bool) $definition->getMandatory(),
                    'group' => $groups?->groupOf($key) ?? [],
                    'classificationStore' => true,
                    // no transformations: the preview knows no key's type
                    'takesColumnAsIs' => true,
                ];
            }
        }

        return new AdHocImportMappingResponse(
            $fields,
            // nothing polls the AI stage here, so it is not started
            $this->mappingSuggestionService->suggest($parameters, self::NO_MAPPING_TARGETS, false)->getSuggestions()
        );
    }

    public function loadClassificationStoreKeys(
        string $classId,
        string $fieldName,
        ?string $search,
        int $start,
        int $limit
    ): ClassificationStoreKeysResponse {
        $this->assertMayCreate($classId);

        $field = $this->classDefinitionResolver->getById($classId)?->getFieldDefinition($fieldName);
        if (!$field instanceof Classificationstore) {
            throw new NotFoundException('Classification store', $fieldName);
        }

        // every key: the import converts a column by the type of the key it goes into
        return $this->dataTypeService->loadClassificationStoreKeys(new ClassificationStoreKeyParameters(
            classId: $classId,
            fieldName: $fieldName,
            transformationResultType: '',
            start: $start,
            limit: $limit,
            searchfilter: $search === '' ? null : $search,
        ));
    }

    // a localized field is found among the class's localized fields,
    // a brick field (`container.brick.field`) in its brick
    private function isMandatory(?ClassDefinition $class, string $key): bool
    {
        $parts = explode('.', $key);
        if (count($parts) !== 3) {
            return $class?->getFieldDefinition($key)?->getMandatory() ?? false;
        }

        [$container, $brickType, $field] = $parts;
        $containerDefinition = $class?->getFieldDefinition($container);
        if (
            !$containerDefinition instanceof Objectbricks
            || !in_array($brickType, $containerDefinition->getAllowedTypes(), true)
        ) {
            return false;
        }

        return $this->brickDefinitionResolver->getByKey($brickType)?->getFieldDefinition($field)?->getMandatory()
            ?? false;
    }
}
