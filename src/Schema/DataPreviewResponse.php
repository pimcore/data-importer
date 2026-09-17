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
    schema: 'BundleDataImporterDataPreviewResponse',
    title: 'Bundle Data Importer Data Preview Response',
    required: ['dataPreview', 'previewRecordIndex'],
    type: 'object'
)]
final class DataPreviewResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(
            description: 'Preview data rows with column metadata',
            type: 'array',
            items: new Items(
                properties: [
                    new Property(
                        property: 'dataIndex',
                        description: 'Column index',
                        type: 'string',
                        example: 'col_0'
                    ),
                    new Property(
                        property: 'label',
                        description: 'Column label',
                        type: 'string',
                        example: 'Product Name'
                    ),
                    new Property(
                        property: 'data',
                        description: 'Cell data value. May be any JSON value (string, number, boolean, ' .
                            'array, object or null) depending on the source data type.',
                        example: 'Example Product'
                    ),
                    new Property(
                        property: 'mapped',
                        description: 'Whether this column is mapped',
                        type: 'boolean',
                        example: false
                    ),
                ],
                type: 'object'
            )
        )]
        private readonly array $dataPreview,
        #[Property(
            description: 'The actual record index that was loaded',
            type: 'integer',
            example: 0
        )]
        private readonly int $previewRecordIndex,
    ) {
    }

    public function getDataPreview(): array
    {
        return $this->dataPreview;
    }

    public function getPreviewRecordIndex(): int
    {
        return $this->previewRecordIndex;
    }
}
