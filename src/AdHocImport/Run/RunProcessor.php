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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport\Run;

use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\Processing\AdHocRowProcessorInterface;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;

/**
 * Works off a run's queue items, one per row. The plan is built once per file and process; the outcome of each row is
 * kept in the run, so another worker can go on. A row that fails counts like any other, so a run always ends.
 *
 * @internal
 */
final class RunProcessor implements AdHocRowProcessorInterface
{
    private ?string $planFileId = null;

    private ?ImportPlan $plan = null;

    public function __construct(
        private readonly RunStoreInterface $runs,
        private readonly AdHocImportDryRunServiceInterface $dryRunService,
        private readonly FileStore $files,
        private readonly RowImporter $rowImporter,
        private readonly RowWriterInterface $writer,
        private readonly RunMessages $messages,
        private readonly RunNotifier $notifier,
        private readonly UserResolverInterface $userResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function processRow(string $queueName, int $userId, string $data): void
    {
        $fileId = RunRecord::fileIdOf($queueName);
        $run = $this->runs->find($userId, $fileId);
        if ($run === null || !$run->isRunning()) {
            return;
        }
        $item = json_decode($data, true);
        $index = is_array($item) ? (int) ($item['row'] ?? 0) : 0;
        $leftOut = is_array($item) && is_array($item['leftOut'] ?? null) ? array_map(intval(...), $item['leftOut']) : [];

        $user = $this->userResolver->getById($userId);
        $outcome = $this->rowOutcome($run, $user, $index, $leftOut);
        $plan = $this->planFileId === $run->fileId ? $this->plan : null;

        $run = $run->counted($outcome);
        $this->runs->save($run);
        if ($outcome->isFailure()) {
            $failure = new RowFailure(
                ($plan->firstRowNumber ?? 2) + $index,
                $outcome->matchValue,
                $outcome->action,
                (string) $outcome->message,
                $this->cells($plan, $index)
            );
            $this->attempt($run, function () use ($run, $failure): void {
                $this->runs->addFailure($run, $failure);
            });
        }

        if ($run->isComplete()) {
            $this->finish($run, $plan, $user);
        }
    }

    /**
     * @param list<int> $leftOut
     */
    private function rowOutcome(RunRecord $run, ?User $user, int $index, array $leftOut): RowOutcome
    {
        // a user deleted meanwhile: nobody is left to write as
        if (!$user instanceof User) {
            return RowOutcome::failed('', null, '');
        }

        $plan = $this->planOf($run, $user);
        if ($plan === null) {
            return RowOutcome::failed('', null, $this->messages->cannotGoOn($user));
        }

        try {
            return $this->rowImporter->import($plan, $user, $index, $leftOut);
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 one row must not end the run, whatever stops it
            // what the row importer does not expect, such as the database going away while it looks for the object
            $this->logger->error(sprintf('Ad hoc import of %s: %s', $run->fileId, $throwable->getMessage()));

            return RowOutcome::failed('', null, $this->messages->cannotGoOn($user));
        }
    }

    private function planOf(RunRecord $run, User $user): ?ImportPlan
    {
        if ($this->planFileId !== $run->fileId) {
            $this->planFileId = $run->fileId;
            $this->plan = null;

            try {
                $this->plan = $this->dryRunService->planForRun(
                    $run->fileId,
                    $run->type,
                    AdHocImportDryRunParameters::fromArray($run->parameters),
                    $run->configuration,
                    $user
                );
            } catch (\Throwable $throwable) { // NOSONAR php:S2221 a plan that cannot be built must not end the run
                // the file or its folder is gone: every row of the run reports that, and the run ends
                $this->logger->error(sprintf('Ad hoc import of %s: %s', $run->fileId, $throwable->getMessage()));
            }
        }

        return $this->plan;
    }

    /**
     * @return list<string>
     */
    private function cells(?ImportPlan $plan, int $index): array
    {
        if ($plan === null) {
            return [];
        }

        $row = $plan->rows[$index] ?? [];

        return array_map(
            static fn (int|string $column): string => is_scalar($row[$column] ?? null) ? (string) $row[$column] : '',
            array_keys($plan->columns)
        );
    }

    private function finish(RunRecord $run, ?ImportPlan $plan, ?User $user): void
    {
        $failures = [];
        $this->attempt($run, function () use ($run, &$failures): void {
            $failures = $this->runs->takeFailures($run);
        });
        $this->attempt($run, function () use ($run, &$failures): void {
            $failures = [...$failures, ...$this->writer->close($run)];
        });
        if ($failures !== []) {
            $this->attempt($run, function () use ($run, $plan, $failures): void {
                $this->storeFailures($run, $plan, $failures);
            });
        }

        $run = $run->finished(time());
        $this->runs->save($run);
        $this->planFileId = null;
        $this->plan = null;

        $this->attempt($run, function () use ($run): void {
            $this->files->deleteUpload($run->userId, $run->fileId, $run->type);
        });
        if ($user instanceof User) {
            $this->attempt($run, function () use ($run, $failures, $user): void {
                $this->notifier->notify($run, $failures, $user);
            });
        }
    }

    /**
     * @param list<RowFailure> $failures
     */
    private function storeFailures(RunRecord $run, ?ImportPlan $plan, array $failures): void
    {
        $this->files->storeFailedRows($run->userId, $run->fileId, (string) json_encode([
            'columns' => $plan === null ? [] : array_values($plan->columns),
            'rows' => array_map(static fn (RowFailure $failure): array => $failure->toArray(), $failures),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * What a run still has to record or tidy up: a step that fails is logged, so that the run ends all the same.
     *
     * @param callable(): void $step
     */
    private function attempt(RunRecord $run, callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $throwable) { // NOSONAR php:S2221 a run always ends, whatever stops one of its steps
            $this->logger->error(sprintf('Ad hoc import of %s: %s', $run->fileId, $throwable->getMessage()));
        }
    }
}
