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
    schema: 'BundleDataImporterUnitDataResponse',
    title: 'Bundle Data Importer Unit Data Response',
    required: ['UnitList'],
    type: 'object'
)]
final class UnitDataResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    /**
     * @param array<int, array{unitId: string, abbreviation: string}> $unitList
     */
    public function __construct(
        #[Property(
            property: 'UnitList',
            description: 'List of quantity value units',
            type: 'array',
            items: new Items(
                properties: [
                    new Property(
                        property: 'unitId',
                        description: 'The unit ID',
                        type: 'string',
                        example: 'kg'
                    ),
                    new Property(
                        property: 'abbreviation',
                        description: 'The unit abbreviation',
                        type: 'string',
                        example: 'kg'
                    ),
                ],
                type: 'object'
            )
        )]
        private readonly array $unitList
    ) {
    }

    /**
     * @return array<int, array{unitId: string, abbreviation: string}>
     */
    public function getUnitList(): array
    {
        return $this->unitList;
    }
}
