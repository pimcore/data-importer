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
    schema: 'BundleDataImporterColumnHeadersResponse',
    title: 'Bundle Data Importer Column Headers Response',
    required: ['columnHeaders'],
    type: 'object'
)]
final class ColumnHeadersResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(
            description: 'Available column headers from the preview data',
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'id', description: 'Column index', type: 'string', example: '0'),
                    new Property(
                        property: 'dataIndex',
                        description: 'Column data index',
                        type: 'string',
                        example: 'col_0'
                    ),
                    new Property(
                        property: 'label',
                        description: 'Column label',
                        type: 'string',
                        example: 'Product Name'
                    ),
                ],
                type: 'object'
            )
        )]
        private readonly array $columnHeaders,
    ) {
    }

    public function getColumnHeaders(): array
    {
        return $this->columnHeaders;
    }
}
