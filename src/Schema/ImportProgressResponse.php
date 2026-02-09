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

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use Pimcore\Bundle\StudioBackendBundle\Util\Schema\AdditionalAttributesInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Trait\AdditionalAttributesTrait;

/**
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterImportProgressResponse',
    title: 'Bundle Data Importer Import Progress Response',
    required: ['isRunning', 'totalItems', 'processedItems', 'progress'],
    type: 'object'
)]
final class ImportProgressResponse implements AdditionalAttributesInterface
{
    use AdditionalAttributesTrait;

    public function __construct(
        #[Property(
            description: 'Whether an import is currently running',
            type: 'boolean',
            example: true
        )]
        private readonly bool $isRunning,
        #[Property(
            description: 'Total number of items to import',
            type: 'integer',
            example: 100
        )]
        private readonly int $totalItems,
        #[Property(
            description: 'Number of items already processed',
            type: 'integer',
            example: 42
        )]
        private readonly int $processedItems,
        #[Property(
            description: 'Progress as a ratio between 0 and 1',
            type: 'number',
            format: 'float',
            example: 0.42
        )]
        private readonly float $progress,
    ) {
    }

    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getProcessedItems(): int
    {
        return $this->processedItems;
    }

    public function getProgress(): float
    {
        return $this->progress;
    }
}
