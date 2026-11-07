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
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\AiSuggestionInput;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\AiSuggestionStage;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\AiSuggestionStatus;
use Pimcore\Bundle\DataImporterBundle\Mapping\Suggestion\ColumnMatcher;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\StaticResolverBundle\Lib\ToolResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\NotFoundException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @phpstan-import-type Column from ColumnMatcher
 * @phpstan-import-type Suggestion from ColumnMatcher
 * @phpstan-import-type Target from ColumnMatcher
 */
final readonly class MappingSuggestionService implements MappingSuggestionServiceInterface
{
    use CurrentUserResolverTrait;

    // keeps a header row a wrong delimiter split into thousands of columns from becoming a huge task
    private const int MAX_AI_COLUMNS = 200;

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
        private EventDispatcherInterface $eventDispatcher,
        private AiSuggestionStage $aiStage,
        private SampleValueReader $sampleValueReader,
        private SecurityServiceInterface $securityService // NOSONAR php:S1068 read by CurrentUserResolverTrait
    ) {
    }

    public function suggest(MappingSuggestionParameters $parameters): MappingSuggestionsResponse
    {
        $classId = $parameters->getClassId();

        if ($this->classDefinitionResolver->getById($classId) === null) {
            throw new NotFoundException('Class', $classId);
        }

        $targets = $this->loadTargets($classId);
        $languages = $this->toolResolver->getValidLanguages();
        $suggestions = $this->columnMatcher->match($parameters->getColumns(), $targets, $languages);
        $aiRequestId = $this->startAiStage($parameters, $suggestions, $targets, $languages);

        return $this->respond(
            $suggestions,
            $aiRequestId === null ? AiSuggestionStatus::None : AiSuggestionStatus::Running,
            $aiRequestId
        );
    }

    public function loadAi(string $requestId): MappingSuggestionsResponse
    {
        $result = $this->aiStage->read($requestId, $this->resolveCurrentUser()->getId());
        if ($result === null) {
            throw new NotFoundException('Mapping suggestion request', $requestId);
        }

        return $this->respond($result->suggestions, $result->status, $requestId);
    }

    /**
     * @param list<Suggestion> $suggestions
     */
    private function respond(
        array $suggestions,
        AiSuggestionStatus $aiStatus,
        ?string $aiRequestId
    ): MappingSuggestionsResponse {
        $response = $this->hydrator->hydrate($suggestions, $aiStatus, $aiRequestId);

        $this->eventDispatcher->dispatch(
            new MappingSuggestionsEvent($response),
            MappingSuggestionsEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * @param list<Suggestion> $suggestions
     * @param list<Target> $targets
     * @param list<string> $languages
     */
    private function startAiStage(
        MappingSuggestionParameters $parameters,
        array $suggestions,
        array $targets,
        array $languages
    ): ?string {
        if (!$this->aiStage->isActive()) {
            return null;
        }

        $columns = $this->openColumns($parameters->getColumns(), $suggestions);
        $openTargets = $this->openTargets($targets, $suggestions);
        if ($columns === [] || $openTargets === []) {
            return null;
        }

        $samples = $this->sampleValues($parameters, $columns);
        // a column with neither a header nor a value gives the agent nothing to go on
        $columns = array_values(array_filter(
            $columns,
            static fn (array $column): bool => trim($column['label']) !== '' || isset($samples[$column['index']])
        ));
        if ($columns === []) {
            return null;
        }

        $taken = [];
        foreach ($suggestions as $suggestion) {
            $taken[] = ['field' => $suggestion['targetFieldName'], 'language' => $suggestion['language']];
        }

        return $this->aiStage->start(
            new AiSuggestionInput($parameters->getClassId(), $columns, $openTargets, $languages, $samples, $taken),
            $this->resolveCurrentUser()->getId()
        );
    }

    /**
     * What the caller asked about and the name comparison left open. A caller sends no column that is mapped or left
     * out already, so none of those reaches the agent.
     *
     * @param list<Column> $columns
     * @param list<Suggestion> $suggestions
     *
     * @return list<Column>
     */
    private function openColumns(array $columns, array $suggestions): array
    {
        $suggested = array_flip(array_column($suggestions, 'sourceIndex'));
        $open = [];
        foreach ($columns as $column) {
            if (!isset($suggested[$column['index']]) && $this->columnMatcher->acceptsLabel($column['label'])) {
                $open[$column['index']] = $column;
            }
        }

        return array_slice(array_values($open), 0, self::MAX_AI_COLUMNS);
    }

    /**
     * A localized field stays open: another column may hold its other languages.
     *
     * @param list<Target> $targets
     * @param list<Suggestion> $suggestions
     *
     * @return list<Target>
     */
    private function openTargets(array $targets, array $suggestions): array
    {
        $suggested = array_flip(array_column($suggestions, 'targetFieldName'));

        return array_values(array_filter(
            $targets,
            static fn (array $target): bool => $target['localized'] || !isset($suggested[$target['key']])
        ));
    }

    /**
     * @param list<Column> $columns
     *
     * @return array<string, list<string>>
     */
    private function sampleValues(MappingSuggestionParameters $parameters, array $columns): array
    {
        $configName = $parameters->getConfigName();
        if ($configName === null || !$this->aiStage->sendsSampleValues()) {
            return [];
        }

        return $this->sampleValueReader->read(
            $configName,
            $parameters->getCurrentConfig(),
            array_column($columns, 'index')
        );
    }

    /**
     * @return list<array{key: string, title: string, localized: bool, transformationResultType: string}>
     */
    private function loadTargets(string $classId): array
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
