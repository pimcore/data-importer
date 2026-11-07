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

/**
 * @phpstan-type Field array{
 *     key: string,
 *     title: string,
 *     localized: bool,
 *     mandatory: bool,
 *     transformationResultType: string,
 *     takesColumnAsIs: bool
 * }
 * @phpstan-type Suggestion array{
 *     sourceIndex: string,
 *     sourceLabel: string,
 *     targetFieldName: string,
 *     targetFieldLabel: string,
 *     transformationResultType: string,
 *     score: int,
 *     language: string|null
 * }
 *
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportMappingResponse',
    title: 'Bundle Data Importer Ad Hoc Import Mapping Response',
    required: ['fields', 'suggestions'],
    type: 'object'
)]
final readonly class AdHocImportMappingResponse
{
    /**
     * @param list<Field> $fields
     * @param list<Suggestion> $suggestions
     */
    public function __construct(
        #[Property(
            description: 'The fields of the class a column can be imported into',
            type: 'array',
            items: new Items(
                required: ['key', 'title', 'localized', 'mandatory', 'transformationResultType', 'takesColumnAsIs'],
                properties: [
                    new Property(property: 'key', description: 'Key of the field', type: 'string', example: 'name'),
                    new Property(
                        property: 'title',
                        description: 'Title of the field',
                        type: 'string',
                        example: 'Name [name]'
                    ),
                    new Property(
                        property: 'localized',
                        description: 'Whether the field holds a value per language',
                        type: 'boolean',
                        example: true
                    ),
                    new Property(
                        property: 'mandatory',
                        description: 'Whether the class requires a value in the field',
                        type: 'boolean',
                        example: false
                    ),
                    new Property(
                        property: 'transformationResultType',
                        description: 'Transformation result type that writes the field',
                        type: 'string',
                        example: 'default'
                    ),
                    new Property(
                        property: 'takesColumnAsIs',
                        description: 'Whether a column goes into the field without a transformation, converted to its type',
                        type: 'boolean',
                        example: true
                    ),
                ],
                type: 'object'
            )
        )]
        private array $fields,
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
                ],
                properties: [
                    new Property(
                        property: 'sourceIndex',
                        description: 'Column data index',
                        type: 'string',
                        example: 'sku'
                    ),
                    new Property(
                        property: 'sourceLabel',
                        description: 'Column name',
                        type: 'string',
                        example: 'Name DE'
                    ),
                    new Property(
                        property: 'targetFieldName',
                        description: 'Key of the suggested field',
                        type: 'string',
                        example: 'name'
                    ),
                    new Property(
                        property: 'targetFieldLabel',
                        description: 'Title of the suggested field',
                        type: 'string',
                        example: 'Name [name]'
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
                ],
                type: 'object'
            )
        )]
        private array $suggestions,
    ) {
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return list<Suggestion>
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }
}
