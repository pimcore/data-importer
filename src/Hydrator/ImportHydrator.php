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

use Pimcore\Bundle\DataImporterBundle\Schema\CronValidationResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportFileStatusResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportProgressResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportStartResponse;

/**
 * @internal
 */
final readonly class ImportHydrator implements ImportHydratorInterface
{
    public function hydrateImportStart(bool $success): ImportStartResponse
    {
        return new ImportStartResponse($success);
    }

    public function hydrateImportProgress(
        bool $isRunning,
        int $totalItems,
        int $processedItems,
        float $progress
    ): ImportProgressResponse {
        return new ImportProgressResponse($isRunning, $totalItems, $processedItems, $progress);
    }

    public function hydrateCronValidation(bool $isValid, string $message): CronValidationResponse
    {
        return new CronValidationResponse($isValid, $message);
    }

    public function hydrateImportFileStatus(
        bool $exists,
        string $message,
        ?string $filePath = null
    ): ImportFileStatusResponse {
        return new ImportFileStatusResponse($exists, $message, $filePath);
    }
}
