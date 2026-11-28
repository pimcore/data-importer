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
 *     matchFieldTitle: string|null,
 *     matchLanguage: string|null,
 *     matchColumn: string|null,
 *     newObjectsFolder: string|null,
 *     columns: list<string>,
 *     columnsBy: 'name'|'position',
 *     unusable: string|null,
 *     format: string|null,
 *     sheet: string|null,
 *     headerRow: bool,
 *     source: string|null,
 *     sourcePath: string|null,
 *     fieldCount: int,
 *     transformedCount: int,
 *     schedule: string|null
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
                    'name', 'classId', 'className', 'matchBy', 'matchField', 'matchFieldTitle', 'matchLanguage',
                    'matchColumn',
                    'newObjectsFolder', 'columns', 'columnsBy', 'unusable', 'format', 'sheet', 'headerRow',
                    'source', 'sourcePath', 'fieldCount', 'transformedCount',
                    'schedule',
                ],
                properties: [
                    new Property(
                        property: 'name', type: 'string', example: 'products',
                        description: 'Name of the configuration'
                    ),
                    new Property(
                        property: 'classId', type: 'string', example: 'PROD', nullable: true,
                        description: 'ID of the class it imports'
                    ),
                    new Property(
                        property: 'className', type: 'string', example: 'Product', nullable: true,
                        description: 'Name of the class it imports'
                    ),
                    new Property(
                        property: 'matchBy', type: 'string', enum: ['id', 'field', 'none'],
                        example: 'field', nullable: true,
                        description: 'What it finds objects by: the object ID or a field, or none to create every row'
                    ),
                    new Property(
                        property: 'matchField', type: 'string', example: 'sku', nullable: true,
                        description: 'The field it finds objects by'
                    ),
                    new Property(
                        property: 'matchFieldTitle', type: 'string', example: 'SKU', nullable: true,
                        description: 'The title of the field it finds objects by, or its key when it has none'
                    ),
                    new Property(
                        property: 'matchLanguage', type: 'string', example: 'de', nullable: true,
                        description: 'The language of a localized field it finds objects by'
                    ),
                    new Property(
                        property: 'matchColumn', type: 'string', example: 'SKU', nullable: true,
                        description: 'The column holding the value it finds objects by; null when it finds none'
                    ),
                    new Property(
                        property: 'newObjectsFolder', type: 'string', example: '/Products/Imported', nullable: true,
                        description: 'Path of the folder it creates new objects in'
                    ),
                    new Property(
                        property: 'columns', type: 'array', items: new Items(type: 'string'), example: ['SKU', 'Name'],
                        description: 'The columns it reads'
                    ),
                    new Property(
                        property: 'columnsBy', type: 'string', enum: ['name', 'position'], example: 'name',
                        description: 'What its columns are named by: the header row\'s names or the position'
                    ),
                    new Property(
                        property: 'unusable', type: 'string', example: null, nullable: true,
                        description: 'Why an ad hoc import cannot use it: class, matching, folder, folder-missing '
                            . 'or folder-not-allowed; null if it can'
                    ),
                    new Property(
                        property: 'format', type: 'string', example: 'xlsx', nullable: true,
                        description: 'The type of file or data it reads: csv, xlsx, json, xml or sql'
                    ),
                    new Property(
                        property: 'sheet', type: 'string', example: 'Products', nullable: true,
                        description: 'The sheet an XLSX configuration reads'
                    ),
                    new Property(
                        property: 'headerRow', type: 'boolean', example: true,
                        description: 'Whether its CSV or XLSX file starts with a header row'
                    ),
                    new Property(
                        property: 'source', type: 'string', example: 'sftp', nullable: true,
                        description: 'Where it loads its data from: sftp, http, asset, upload, push or sql'
                    ),
                    new Property(
                        property: 'sourcePath', type: 'string', example: '/in/products.xlsx', nullable: true,
                        description: 'The remote path of an SFTP source or the asset path of an asset source'
                    ),
                    new Property(
                        property: 'fieldCount', type: 'integer', example: 18,
                        description: 'The rows of its mapping'
                    ),
                    new Property(
                        property: 'transformedCount', type: 'integer', example: 6,
                        description: 'The rows of its mapping with transformations'
                    ),
                    new Property(
                        property: 'schedule', type: 'string', example: '0 2 * * *', nullable: true,
                        description: 'The cron definition it runs on, null when it runs only by hand'
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
