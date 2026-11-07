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
 * @phpstan-type Field array{key: string|null, language: string|null, label: string}
 * @phpstan-type Row array{
 *     row: int,
 *     matchValue: string,
 *     action: string|null,
 *     error: string|null,
 *     values: list<array{old: string|null, new: string|null}>,
 *     invalid: list<array{field: int|null, label: string, value: string, message: string}>
 * }
 *
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportDryRunResponse',
    title: 'Bundle Data Importer Ad Hoc Import Dry Run Response',
    required: ['matchColumn', 'newObjectsFolder', 'fields', 'rows'],
    type: 'object'
)]
final readonly class AdHocImportDryRunResponse
{
    /**
     * @param list<Field> $fields
     * @param list<Row> $rows
     */
    public function __construct(
        #[Property(description: 'How the column holding the match value is called', type: 'string', example: 'SKU')]
        private string $matchColumn,
        #[Property(description: 'Path of the folder new objects go to', type: 'string', example: '/Products')]
        private string $newObjectsFolder,
        #[Property(
            description: 'The fields the import writes and the preview shows, beside the field matched by',
            type: 'array',
            items: new Items(
                required: ['key', 'language', 'label'],
                properties: [
                    new Property(
                        property: 'key',
                        description: 'Key of the field; null for a target the preview cannot read',
                        type: 'string',
                        nullable: true
                    ),
                    new Property(
                        property: 'language',
                        description: 'Language of a localized field',
                        type: 'string',
                        nullable: true
                    ),
                    new Property(property: 'label', description: 'How the field is called', type: 'string'),
                ],
                type: 'object'
            )
        )]
        private array $fields,
        #[Property(
            description: 'Every row of the file, in its order',
            type: 'array',
            items: new Items(
                required: ['row', 'matchValue', 'action', 'error', 'values', 'invalid'],
                properties: [
                    new Property(
                        property: 'row',
                        description: 'Number of the row in the file, the header row counted',
                        type: 'integer'
                    ),
                    new Property(property: 'matchValue', description: 'The value matched by', type: 'string'),
                    new Property(
                        property: 'action',
                        description: 'update or create; null for a row that means no object and is skipped',
                        type: 'string',
                        enum: ['update', 'create'],
                        nullable: true
                    ),
                    new Property(
                        property: 'error',
                        description: 'Why the row means no object',
                        type: 'string',
                        nullable: true
                    ),
                    new Property(
                        property: 'values',
                        description: 'Per field, the value before and after the import',
                        type: 'array',
                        items: new Items(
                            required: ['old', 'new'],
                            properties: [
                                new Property(property: 'old', type: 'string', nullable: true),
                                new Property(property: 'new', type: 'string', nullable: true),
                            ],
                            type: 'object'
                        )
                    ),
                    new Property(
                        property: 'invalid',
                        description: 'The values that cannot go into their field; skipping the row or leaving the '
                            . 'field unchanged',
                        type: 'array',
                        items: new Items(
                            required: ['field', 'label', 'value', 'message'],
                            properties: [
                                new Property(
                                    property: 'field',
                                    description: 'Position of the field among fields; null for the field matched by',
                                    type: 'integer',
                                    nullable: true
                                ),
                                new Property(property: 'label', description: 'How the field is called', type: 'string'),
                                new Property(property: 'value', description: 'The value in the file', type: 'string'),
                                new Property(property: 'message', description: 'What is wrong', type: 'string'),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                type: 'object'
            )
        )]
        private array $rows,
    ) {
    }

    public function getMatchColumn(): string
    {
        return $this->matchColumn;
    }

    public function getNewObjectsFolder(): string
    {
        return $this->newObjectsFolder;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return list<Row>
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}
