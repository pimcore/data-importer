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
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportWaitingRunsResponse',
    title: 'Bundle Data Importer Ad Hoc Import Waiting Runs Response',
    required: ['items'],
    type: 'object'
)]
final readonly class AdHocImportWaitingRunsResponse
{
    /**
     * @param list<array{fileId: string, fileName: string}> $items
     */
    public function __construct(
        #[Property(
            description: 'The imports into the folder that wait for a decision of the user',
            type: 'array',
            items: new Items(
                required: ['fileId', 'fileName'],
                properties: [
                    new Property(property: 'fileId', description: 'ID of the imported file', type: 'string'),
                    new Property(
                        property: 'fileName',
                        description: 'Name of the imported file',
                        type: 'string',
                        example: 'autumn.xlsx'
                    ),
                ],
                type: 'object'
            )
        )]
        private array $items,
    ) {
    }

    /**
     * @return list<array{fileId: string, fileName: string}>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
