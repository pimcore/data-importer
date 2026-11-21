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

use Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface PreviewDataServiceInterface
{
    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     * @throws EnvironmentException
     * @throws MaxFileSizeExceededException
     * @throws Exception
     */
    public function uploadPreviewData(string $name, UploadedFile $file, ?PreviewScope $scope = null): void;

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     * @throws EnvironmentException
     * @throws MaxFileSizeExceededException
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    public function copyPreviewData(string $name, ?array $currentConfig, ?PreviewScope $scope = null): void;

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     * @throws EnvironmentException
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    public function loadPreviewData(
        string $name,
        ?array $currentConfig,
        int $recordNumber,
        ?PreviewScope $scope = null
    ): DataPreviewResponse;

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     */
    public function loadColumnHeaders(
        string $name,
        ?array $currentConfig,
        ?PreviewScope $scope = null,
    ): ColumnHeadersResponse;
}
