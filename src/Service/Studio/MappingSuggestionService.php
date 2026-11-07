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

use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\MappingSuggestionsEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\MappingSuggestionHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\ColumnMatcher;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\StaticResolverBundle\Lib\ToolResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class MappingSuggestionService implements MappingSuggestionServiceInterface
{
    // Result types a suggestion can write. A field offered by several keeps the first, so simple fields stay on the
    // default one.
    private const array TRANSFORMATION_RESULT_TYPES = [
        TransformationDataTypeService::DEFAULT_TYPE => [
            TransformationDataTypeService::DEFAULT_TYPE,
            TransformationDataTypeService::NUMERIC,
        ],
        TransformationDataTypeService::DEFAULT_ARRAY => TransformationDataTypeService::DEFAULT_ARRAY,
        TransformationDataTypeService::BOOLEAN => TransformationDataTypeService::BOOLEAN,
        TransformationDataTypeService::DATE => TransformationDataTypeService::DATE,
        TransformationDataTypeService::QUANTITY_VALUE => TransformationDataTypeService::QUANTITY_VALUE,
        TransformationDataTypeService::ASSET => TransformationDataTypeService::ASSET,
        TransformationDataTypeService::ASSET_ARRAY => TransformationDataTypeService::ASSET_ARRAY,
        TransformationDataTypeService::GALLERY => TransformationDataTypeService::GALLERY,
        TransformationDataTypeService::DATA_OBJECT => TransformationDataTypeService::DATA_OBJECT,
        TransformationDataTypeService::DATA_OBJECT_ARRAY => TransformationDataTypeService::DATA_OBJECT_ARRAY,
    ];

    public function __construct(
        private DataTypeServiceInterface $dataTypeService,
        private ClassDefinitionResolverInterface $classDefinitionResolver,
        private ToolResolverInterface $toolResolver,
        private ColumnMatcher $columnMatcher,
        private MappingSuggestionHydratorInterface $hydrator,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function suggest(MappingSuggestionParameters $parameters): MappingSuggestionsResponse
    {
        $classId = $parameters->getClassId();

        if ($this->classDefinitionResolver->getById($classId) === null) {
            throw new NotFoundException('Class', $classId);
        }

        $response = $this->hydrator->hydrate(
            $this->columnMatcher->match(
                $parameters->getColumns(),
                $this->loadTargets($classId),
                $this->toolResolver->getValidLanguages()
            )
        );

        $this->eventDispatcher->dispatch(
            new MappingSuggestionsEvent($response),
            MappingSuggestionsEvent::EVENT_NAME
        );

        return $response;
    }

    public function loadTargets(string $classId): array
    {
        $targets = [];
        foreach (self::TRANSFORMATION_RESULT_TYPES as $resultType => $requestedTypes) {
            $attributes = $this->dataTypeService
                ->loadClassAttributes($classId, $requestedTypes, false, true, false)
                ->getAttributes();

            foreach ($attributes as $attribute) {
                $targets[$attribute['key']] ??= [
                    'key' => $attribute['key'],
                    'title' => $attribute['title'],
                    'localized' => (bool) ($attribute['localized'] ?? false),
                    'transformationResultType' => $resultType,
                ];
            }
        }

        return array_values($targets);
    }
}
