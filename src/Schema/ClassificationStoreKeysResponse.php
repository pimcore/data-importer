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
                    new Property(property: 'keyId', description: 'Key ID', type: 'integer'),
                    new Property(property: 'groupId', description: 'Group ID', type: 'integer'),
                    new Property(property: 'keyName', description: 'Key name', type: 'string'),
                    new Property(property: 'keyDescription', description: 'Key description', type: 'string'),
                    new Property(property: 'id', description: 'Combined group-key ID', type: 'string'),
                    new Property(property: 'sorter', description: 'Sort order', type: 'integer'),
                    new Property(property: 'groupName', description: 'Group name', type: 'string'),
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
