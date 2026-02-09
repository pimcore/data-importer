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

namespace Pimcore\Bundle\DataImporterBundle\Hydrator;

use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;

/**
 * @internal
 */
interface PreviewHydratorInterface
{
    public function hydrateDataPreview(array $dataPreview, int $recordNumber): DataPreviewResponse;

    public function hydrateColumnHeaders(array $columnHeaders): ColumnHeadersResponse;

    /**
     * Load available column headers from preview data.
     * Gracefully returns empty array on failure (no preview file, interpreter error, etc.)
     */
    public function loadAvailableColumnHeaders(string $name, array $config): array;

    /**
     * Check if an array can be safely JSON-encoded.
     */
    public function isValidJson(array $array): bool;
}
