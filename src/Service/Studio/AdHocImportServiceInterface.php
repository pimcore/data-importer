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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface AdHocImportServiceInterface
{
    /**
     * @throws NotFoundHttpException if $folderId is not a data object folder
     * @throws ForbiddenException if the user may not create and save in that folder
     * @throws MaxFileSizeExceededException
     * @throws EnvironmentException if the upload did not arrive
     */
    public function upload(UploadedFile $file, int $folderId): AdHocImportFileResponse;

    /**
     * @throws NotFoundHttpException if the user has no such file
     */
    public function inspect(string $fileId, SettingsInput $settings): AdHocImportFileResponse;

    /**
     * @throws NotFoundHttpException if the user has no such file
     */
    public function delete(string $fileId): void;

    /**
     * @throws ForbiddenException if the user may not create objects of that class
     */
    public function loadMatchFields(string $classId): ClassAttributesResponse;
}
