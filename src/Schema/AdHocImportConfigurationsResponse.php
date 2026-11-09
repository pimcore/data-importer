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
 * @phpstan-type ImportConfiguration array{
 *     name: string,
 *     classId: string|null,
 *     className: string|null,
 *     matchBy: 'id'|'field'|'none'|null,
 *     matchField: string|null,
 *     matchLanguage: string|null,
 *     matchColumn: string|null,
 *     newObjectsFolder: string|null,
 *     columns: list<string>,
 *     columnsBy: 'name'|'position',
 *     unusable: string|null
 * }
 *
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportConfigurationsResponse',
    title: 'Bundle Data Importer Ad Hoc Import Configurations Response',
    required: ['configurations'],
    type: 'object'
)]
final readonly class AdHocImportConfigurationsResponse
{
    /**
     * @param list<ImportConfiguration> $configurations
     */
    public function __construct(
        #[Property(
            description: 'The data object import configurations the user may read, by name',
            type: 'array',
            items: new Items(
                required: [
                    'name',
                    'classId',
                    'className',
                    'matchBy',
                    'matchField',
                    'matchLanguage',
                    'matchColumn',
                    'newObjectsFolder',
                    'columns',
                    'columnsBy',
                    'unusable',
                ],
                properties: [
                    new Property(
                        property: 'name',
                        description: 'Name of the configuration',
                        type: 'string',
                        example: 'products'
                    ),
                    new Property(
                        property: 'classId',
                        description: 'ID of the class it imports',
                        type: 'string',
                        example: 'PROD',
                        nullable: true
                    ),
                    new Property(
                        property: 'className',
                        description: 'Name of the class it imports',
                        type: 'string',
                        example: 'Product',
                        nullable: true
                    ),
                    new Property(
                        property: 'matchBy',
                        description: 'What it finds objects by: the object ID or a field, or none to create every row',
                        type: 'string',
                        enum: ['id', 'field', 'none'],
                        example: 'field',
                        nullable: true
                    ),
                    new Property(
                        property: 'matchField',
                        description: 'The field it finds objects by',
                        type: 'string',
                        example: 'sku',
                        nullable: true
                    ),
                    new Property(
                        property: 'matchLanguage',
                        description: 'The language of a localized field it finds objects by',
                        type: 'string',
                        example: 'de',
                        nullable: true
                    ),
                    new Property(
                        property: 'matchColumn',
                        description: 'The column holding the value it finds objects by; null when it finds none',
                        type: 'string',
                        example: 'SKU',
                        nullable: true
                    ),
                    new Property(
                        property: 'newObjectsFolder',
                        description: 'Path of the folder it creates new objects in',
                        type: 'string',
                        example: '/Products/Imported',
                        nullable: true
                    ),
                    new Property(
                        property: 'columns',
                        description: 'The columns it reads',
                        type: 'array',
                        items: new Items(type: 'string'),
                        example: ['SKU', 'Name']
                    ),
                    new Property(
                        property: 'columnsBy',
                        description: 'What its columns are named by: the header row\'s names or the position',
                        type: 'string',
                        enum: ['name', 'position'],
                        example: 'name'
                    ),
                    new Property(
                        property: 'unusable',
                        description: 'Why an ad hoc import cannot use it: class, matching, folder, folder-missing '
                            . 'or folder-not-allowed; null if it can',
                        type: 'string',
                        example: null,
                        nullable: true
                    ),
                ],
                type: 'object'
            )
        )]
        private array $configurations,
    ) {
    }

    /**
     * @return list<ImportConfiguration>
     */
    public function getConfigurations(): array
    {
        return $this->configurations;
    }
}
