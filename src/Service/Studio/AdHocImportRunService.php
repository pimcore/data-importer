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

use Pimcore\Bundle\DataImporterBundle\AdHocImport\CsvText;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunRow;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunQueueInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunStoreInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SpreadsheetCell;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Starts the run of a file over the Data Importer's queue, in file order, and answers how it goes.
 *
 * @internal
 */
final readonly class AdHocImportRunService implements AdHocImportRunServiceInterface
{
    use CurrentUserResolverTrait;

    public function __construct(
        private SecurityServiceInterface $securityService, // NOSONAR php:S1068 read by CurrentUserResolverTrait
        private AdHocImportDryRunServiceInterface $dryRunService,
        private RunStoreInterface $runs,
        private FileStore $files,
        private RunQueueInterface $queue,
        private RowWriterInterface $writer,
        private DryRunMessages $messages,
    ) {
    }

    public function start(string $fileId, AdHocImportRunParameters $parameters): AdHocImportRunResponse
    {
        $user = $this->resolveCurrentUser();
        if ($this->runs->find($user->getId(), $fileId)?->isRunning()) {
            throw new InvalidArgumentException(sprintf('File %s is being imported.', $fileId));
        }

        $settings = $parameters->getSettings();
        $checked = $this->dryRunService->check($fileId, $settings, $user);
        $imported = $this->importedRows($checked->results, $parameters->getErrorMode());
        if ($imported === []) {
            throw new InvalidArgumentException(sprintf('File %s has no row to import.', $fileId));
        }

        $type = $this->files->findType($user->getId(), $fileId)
            ?? throw new NotFoundHttpException(sprintf('File %s not found', $fileId));
        $run = new RunRecord(
            $user->getId(),
            $fileId,
            $type,
            $parameters->getFileName(),
            $settings->toArray(),
            $checked->plan->configuration,
            $parameters->getErrorMode(),
            count($imported),
            count($checked->results) - count($imported),
            $checked->plan->matchColumn,
            $checked->plan->dryRun->newObjectFolder,
            $checked->plan->newObjectFolderId,
            trim($user->getFirstname() . ' ' . $user->getLastname()) ?: $user->getName()
        );

        $this->runs->save($run);

        try {
            $this->writer->open($run);
            $this->queue->enqueue($run, $imported);
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 whatever stopped it, a run short of rows never ends
            $this->runs->delete($user->getId(), $fileId);

            throw $throwable;
        }

        return $this->response($run, []);
    }

    public function status(string $fileId): AdHocImportRunResponse
    {
        $user = $this->resolveCurrentUser();
        $run = $this->requireRun($user, $fileId);

        return $this->response($run, $this->failures($user, $fileId)['rows']);
    }

    public function failedRows(string $fileId): string
    {
        $user = $this->resolveCurrentUser();
        $failed = $this->failures($user, $fileId);
        if ($failed['rows'] === []) {
            throw new NotFoundHttpException(sprintf('No failed rows of file %s found', $fileId));
        }

        $lines = [[
            $this->messages->columnName('row', $user),
            ...array_map(SpreadsheetCell::safe(...), $failed['columns']),
            $this->messages->columnName('error', $user),
        ]];
        foreach ($failed['rows'] as $row) {
            $lines[] = [
                $row['row'],
                ...array_map(SpreadsheetCell::safe(...), $row['values']),
                SpreadsheetCell::safe($row['message']),
            ];
        }

        return CsvText::of($lines);
    }

    public function discard(string $fileId): void
    {
        $user = $this->resolveCurrentUser();
        if ($this->requireRun($user, $fileId)->isRunning()) {
            throw new InvalidArgumentException(sprintf('File %s is being imported.', $fileId));
        }

        $this->runs->delete($user->getId(), $fileId);
        $this->files->deleteRunOutput($user->getId(), $fileId);
    }

    /**
     * @param list<DryRunRow> $results
     *
     * @return array<int, list<int>> per position in the file of the rows to import, the writers it goes without
     */
    private function importedRows(array $results, string $errorMode): array
    {
        $imported = [];
        foreach ($results as $index => $result) {
            if ($errorMode === AdHocImportDryRunServiceInterface::SKIP_ROWS) {
                if (!$result->hasErrors()) {
                    $imported[$index] = [];
                }
            } elseif ($result->importsWithoutInvalidValues()) {
                $imported[$index] = $result->leftOut;
            }
        }

        return $imported;
    }

    private function requireRun(User $user, string $fileId): RunRecord
    {
        return $this->runs->find($user->getId(), $fileId)
            ?? throw new NotFoundHttpException(sprintf('No run of file %s found', $fileId));
    }

    /**
     * @return array{
     *     columns: list<string>,
     *     rows: list<array{
     *         row: int,
     *         matchValue: string,
     *         action: string|null,
     *         message: string,
     *         values: list<string>
     *     }>
     * }
     */
    private function failures(User $user, string $fileId): array
    {
        $stored = $this->files->readFailedRows($user->getId(), $fileId);
        $failed = $stored === null ? null : json_decode($stored, true);

        return is_array($failed) ? $failed : ['columns' => [], 'rows' => []];
    }

    /**
     * @param list<array{row: int, matchValue: string, action: string|null, message: string, values: list<string>}> $rows
     */
    private function response(RunRecord $run, array $rows): AdHocImportRunResponse
    {
        return new AdHocImportRunResponse(
            $run->state,
            $run->total,
            $run->processed(),
            $run->updated,
            $run->created,
            $run->failed,
            $run->skipped,
            $run->errorMode,
            $run->fileName,
            $run->matchColumn,
            $run->newObjectsFolder,
            $run->newObjectsFolderId,
            $run->userName,
            $run->finishedAt,
            array_map(static fn (array $row): array => [
                'row' => $row['row'],
                'matchValue' => $row['matchValue'],
                'action' => $row['action'],
                'message' => $row['message'],
            ], $rows)
        );
    }
}
