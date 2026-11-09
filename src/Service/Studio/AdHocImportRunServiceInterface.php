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

use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunResponse;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
interface AdHocImportRunServiceInterface
{
    /**
     * Checks the file once more as its dry run does and queues every row its error mode imports.
     *
     * @throws NotFoundHttpException if the user has no such file, or the folder or configuration is not found
     * @throws ForbiddenException if the user may not import into the folders or create objects of the class
     * @throws InvalidArgumentException if the file cannot be imported, has no row to import or is being imported
     */
    public function start(string $fileId, AdHocImportRunParameters $parameters): AdHocImportRunResponse;

    /**
     * @throws NotFoundHttpException if the user has no run of such a file
     */
    public function status(string $fileId): AdHocImportRunResponse;

    /**
     * The rows the run could not save, as CSV.
     *
     * @throws NotFoundHttpException if the user has no failed rows of such a file
     */
    public function failedRows(string $fileId): string;

    /**
     * Forgets a finished run and what it left for download. A run waiting for a decision is discarded: its branch is
     * closed without a merge, and its file is deleted.
     *
     * @throws NotFoundHttpException if the user has no run of such a file
     * @throws InvalidArgumentException if the run still goes on
     */
    public function discard(string $fileId): void;

    /**
     * Merges the rows of a run waiting for a decision that can be saved, and leaves out the others.
     *
     * @throws NotFoundHttpException if the user has no run of such a file
     * @throws InvalidArgumentException if the run waits for no decision
     */
    public function mergeLeavingOut(string $fileId): AdHocImportRunResponse;

    /**
     * Closes the branch of a run waiting for a decision and forgets the run, keeping its file for another run.
     *
     * @throws NotFoundHttpException if the user has no run of such a file
     * @throws InvalidArgumentException if the run waits for no decision
     */
    public function withdraw(string $fileId): void;

    /**
     * The runs of the user into a folder that wait for a decision.
     *
     * @return list<array{fileId: string, fileName: string}>
     */
    public function waitingRuns(int $folderId): array;
}
