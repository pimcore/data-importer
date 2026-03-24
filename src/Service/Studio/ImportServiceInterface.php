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
use Pimcore\Bundle\DataImporterBundle\Exception\QueueNotEmptyException;
use Pimcore\Bundle\DataImporterBundle\Schema\CronValidationResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportFileStatusResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportProgressResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportStartResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface ImportServiceInterface
{
    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     * @throws QueueNotEmptyException
     */
    public function startImport(string $name): ImportStartResponse;

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     * @throws Exception
     */
    public function checkImportProgress(string $name): ImportProgressResponse;

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     * @throws Exception
     */
    public function cancelExecution(string $name): void;

    public function validateCronExpression(string $cronExpression): CronValidationResponse;

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     * @throws Exception
     */
    public function uploadImportFile(string $name, UploadedFile $file): void;

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenException
     */
    public function hasImportFileUploaded(string $name): ImportFileStatusResponse;
}
