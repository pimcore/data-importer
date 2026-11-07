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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterMappingSuggestionParameters',
    title: 'Bundle Data Importer Mapping Suggestion Parameters',
    required: ['classId', 'columns'],
    type: 'object'
)]
final readonly class MappingSuggestionParameters
{
    // the most columns a spreadsheet can have
    private const int MAX_COLUMNS = 16384;

    /**
     * @param list<array{index: string, label: string}> $columns
     */
    public function __construct(
        #[Property(
            description: 'The ID of the data object class to suggest fields of',
            type: 'string',
            example: 'Product'
        )]
        #[Assert\NotBlank]
        private string $classId,
        #[Property(
            description: 'The source columns to suggest a field for',
            type: 'array',
            items: new Items(
                required: ['index', 'label'],
                properties: [
                    new Property(property: 'index', description: 'Column data index', type: 'string', example: 'col_0'),
                    new Property(
                        property: 'label',
                        description: 'Column name',
                        type: 'string',
                        example: 'Bezeichnung DE'
                    ),
                ],
                type: 'object'
            )
        )]
        #[Assert\Count(max: self::MAX_COLUMNS)]
        #[Assert\All(constraints: [
            new Assert\Collection(fields: [
                'index' => [new Assert\NotNull(), new Assert\Type('string')],
                'label' => [new Assert\NotNull(), new Assert\Type('string')],
            ]),
        ])]
        private array $columns,
    ) {
    }

    public function getClassId(): string
    {
        return $this->classId;
    }

    /**
     * @return list<array{index: string, label: string}>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
}
