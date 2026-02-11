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
    schema: 'BundleDataImporterConnectionsResponse',
    title: 'Bundle Data Importer Connections Response',
    required: ['connections'],
    type: 'object'
)]
final class ConnectionsResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    /**
     * @param array<int, array{name: string, value: string}> $connections
     */
    public function __construct(
        #[Property(
            property: 'connections',
            description: 'List of available Doctrine database connections',
            type: 'array',
            items: new Items(
                properties: [
                    new Property(
                        property: 'name',
                        description: 'The connection name',
                        type: 'string',
                        example: 'default'
                    ),
                    new Property(
                        property: 'value',
                        description: 'The connection service identifier',
                        type: 'string',
                        example: 'doctrine.dbal.default_connection'
                    ),
                ],
                type: 'object'
            )
        )]
        private readonly array $connections
    ) {
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }
}
