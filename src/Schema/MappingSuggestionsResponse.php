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

namespace Pimcore\Bundle\DataImporterBundle\Schema;

use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\StudioBackendBundle\Util\Schema\AdditionalAttributesInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Trait\AdditionalAttributesTrait;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterMappingSuggestionsResponse',
    title: 'Bundle Data Importer Mapping Suggestions Response',
    required: ['suggestions', 'aiStatus', 'aiRequestId'],
    type: 'object'
)]
final class MappingSuggestionsResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    /**
     * @param list<array{
     *     sourceIndex: string,
     *     sourceLabel: string,
     *     targetFieldName: string,
     *     targetFieldLabel: string,
     *     transformationResultType: string,
     *     score: int,
     *     language: string|null,
     *     source: string
     * }> $suggestions
     */
    public function __construct(
        #[Property(
            description: 'The suggested field per column, best score first; a column without a match has none',
            type: 'array',
            items: new Items(
                required: [
                    'sourceIndex',
                    'sourceLabel',
                    'targetFieldName',
                    'targetFieldLabel',
                    'transformationResultType',
                    'score',
                    'language',
                    'source',
                ],
                properties: [
                    new Property(
                        property: 'sourceIndex',
                        description: 'Column data index',
                        type: 'string',
                        example: 'col_0'
                    ),
                    new Property(
                        property: 'sourceLabel',
                        description: 'Column name',
                        type: 'string',
                        example: 'Bezeichnung DE'
                    ),
                    new Property(
                        property: 'targetFieldName',
                        description: 'Key of the suggested field',
                        type: 'string',
                        example: 'bezeichnung'
                    ),
                    new Property(
                        property: 'targetFieldLabel',
                        description: 'Title of the suggested field',
                        type: 'string',
                        example: 'Bezeichnung [bezeichnung]'
                    ),
                    new Property(
                        property: 'transformationResultType',
                        description: 'Transformation result type that writes the suggested field',
                        type: 'string',
                        example: 'default'
                    ),
                    new Property(
                        property: 'score',
                        description: 'How well the names match, from 0 to 100',
                        type: 'integer',
                        example: 100
                    ),
                    new Property(
                        property: 'language',
                        description: 'Language guessed from the column name, for a localized field',
                        type: 'string',
                        example: 'de',
                        nullable: true
                    ),
                    new Property(
                        property: 'source',
                        description: 'Where the suggestion comes from: the name comparison or the AI stage',
                        type: 'string',
                        enum: ['name', 'ai'],
                        example: 'name'
                    ),
                ],
                type: 'object'
            )
        )]
        private readonly array $suggestions,
        #[Property(
            description: 'Where the AI stage stands; while it is running, load its suggestions with the request id',
            type: 'string',
            enum: ['none', 'running', 'finished', 'failed'],
            example: 'none'
        )]
        private readonly string $aiStatus = 'none',
        #[Property(
            description: 'The id to load the suggestions of the AI stage with; null when it started no task',
            type: 'string',
            example: null,
            nullable: true
        )]
        private readonly ?string $aiRequestId = null,
    ) {
    }

    /**
     * @return list<array{
     *     sourceIndex: string,
     *     sourceLabel: string,
     *     targetFieldName: string,
     *     targetFieldLabel: string,
     *     transformationResultType: string,
     *     score: int,
     *     language: string|null,
     *     source: string
     * }>
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function getAiStatus(): string
    {
        return $this->aiStatus;
    }

    public function getAiRequestId(): ?string
    {
        return $this->aiRequestId;
    }
}
