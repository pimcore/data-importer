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
    schema: 'BundleDataImporterClassificationStoreKeysResponse',
    title: 'Bundle Data Importer Classification Store Keys Response',
    required: ['data', 'total'],
    type: 'object'
)]
final class ClassificationStoreKeysResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(
            description: 'List of classification store key-group relations',
            type: 'array',
            items: new Items(
                properties: [
                    new Property(property: 'keyId', description: 'Key ID', type: 'integer', example: 5),
                    new Property(property: 'groupId', description: 'Group ID', type: 'integer', example: 1),
                    new Property(property: 'keyName', description: 'Key name', type: 'string', example: 'Width'),
                    new Property(
                        property: 'keyDescription',
                        description: 'Key description',
                        type: 'string',
                        example: 'Width of the product'
                    ),
                    new Property(
                        property: 'id',
                        description: 'Combined group-key ID',
                        type: 'string',
                        example: '1-5'
                    ),
                    new Property(property: 'sorter', description: 'Sort order', type: 'integer', example: 0),
                    new Property(
                        property: 'groupName',
                        description: 'Group name',
                        type: 'string',
                        example: 'Dimensions'
                    ),
                ],
                type: 'object'
            )
        )]
        private readonly array $data,
        #[Property(description: 'Total count of matching records', type: 'integer', example: 42)]
        private readonly int $total,
    ) {
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
