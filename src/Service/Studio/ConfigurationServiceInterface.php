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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationDetail;
use Pimcore\Bundle\DataImporterBundle\Schema\CronValidationResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportFileStatusResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportProgressResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportStartResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultTypeResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
interface ConfigurationServiceInterface
{
    /**
     * @throws \Exception
     */
    public function getConfiguration(string $name): ConfigurationDetail;

    /**
     * @throws \Exception
     */
    public function saveConfiguration(string $name, array $configuration, int $modificationDate): ConfigurationDetail;

    /**
     * @throws \Exception
     */
    public function uploadPreviewData(string $name, UploadedFile $file): void;

    /**
     * @throws \Exception
     */
    public function copyPreviewData(string $name, ?array $currentConfig): void;

    /**
     * @throws \Exception
     */
    public function loadPreviewData(string $name, ?array $currentConfig, int $recordNumber): DataPreviewResponse;

    /**
     * @throws \Exception
     */
    public function loadColumnHeaders(string $name, ?array $currentConfig): ColumnHeadersResponse;

    /**
     * @throws \Exception
     */
    public function loadTransformationResultPreviews(string $name, ?array $currentConfig, int $recordNumber): TransformationResultPreviewsResponse;

    /**
     * @throws \Exception
     */
    public function calculateTransformationResultType(string $name, array $currentConfig): TransformationResultTypeResponse;

    /**
     * @throws \Exception
     */
    public function startImport(string $name): ImportStartResponse;

    /**
     * @throws \Exception
     */
    public function checkImportProgress(string $name): ImportProgressResponse;

    /**
     * @throws \Exception
     */
    public function cancelExecution(string $name): void;

    public function validateCronExpression(string $cronExpression): CronValidationResponse;

    /**
     * @throws \Exception
     */
    public function uploadImportFile(string $name, UploadedFile $file): void;

    /**
     * @throws \Exception
     */
    public function hasImportFileUploaded(string $name): ImportFileStatusResponse;
}
