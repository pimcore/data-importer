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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\CheckedFile;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface AdHocImportDryRunServiceInterface
{
    public const string SKIP_ROWS = 'skip';

    public const string WITHOUT_INVALID_VALUES = 'values';

    /**
     * Checks every row of the file as its import would take it and writes nothing; keeps the rows it would skip for
     * download.
     *
     * @throws NotFoundHttpException if the user has no such file, or the folder or configuration is not found
     * @throws ForbiddenException if the user may not import into the folders or create objects of the class
     * @throws InvalidArgumentException if the file, its settings, the match or the mapping cannot be imported
     */
    public function dryRun(string $fileId, AdHocImportDryRunParameters $parameters): AdHocImportDryRunResponse;

    /**
     * What dryRun() does, for a caller that goes on with the file: the plan the rows were checked by and each row's
     * result. The current user's rights are checked, as for a dry run.
     *
     * @throws NotFoundHttpException if the user has no such file, or the folder or configuration is not found
     * @throws ForbiddenException if the user may not import into the folders or create objects of the class
     * @throws InvalidArgumentException if the file, its settings, the match or the mapping cannot be imported
     */
    public function check(string $fileId, AdHocImportDryRunParameters $parameters, User $user): CheckedFile;

    /**
     * The plan of a run already started, built again where no current user exists; a run checks every object itself.
     *
     * @param array{configuration: array<mixed>, mapping: array<int|string, mixed>}|null $configuration the
     *     configuration as it was when the run started
     *
     * @throws NotFoundHttpException if the folder is gone
     * @throws InvalidArgumentException if the file or the mapping cannot be imported
     */
    public function planForRun(
        string $fileId,
        FileType $type,
        AdHocImportDryRunParameters $parameters,
        ?array $configuration,
        User $user
    ): ImportPlan;

    /**
     * The rows the last dry run of the file would skip, as CSV: with SKIP_ROWS every row with an error, with
     * WITHOUT_INVALID_VALUES only those that mean no object.
     *
     * @throws NotFoundHttpException if the user has no dry run of such a file
     * @throws InvalidArgumentException for another error mode
     */
    public function skippedRows(string $fileId, string $errorMode): string;
}
