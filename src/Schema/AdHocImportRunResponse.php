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

namespace Pimcore\Bundle\DataImporterBundle\Schema;

use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

/**
 * @phpstan-type Failure array{row: int, matchValue: string, action: string|null, message: string}
 *
 * @internal
 */
#[Schema(
    schema: 'BundleDataImporterAdHocImportRunResponse',
    title: 'Bundle Data Importer Ad Hoc Import Run Response',
    required: [
        'state',
        'total',
        'processed',
        'updated',
        'created',
        'failed',
        'skipped',
        'errorMode',
        'fileName',
        'matchColumn',
        'newObjectsFolder',
        'newObjectsFolderId',
        'userName',
        'finishedAt',
        'throughBranch',
        'mergeTotal',
        'mergeDone',
        'notice',
        'failures',
    ],
    type: 'object'
)]
final readonly class AdHocImportRunResponse
{
    /**
     * @param list<Failure> $failures
     */
    public function __construct(
        #[Property(
            description: 'Whether its rows are being written, its branch is being merged, the user has to decide on '
                . 'rows that cannot be saved, or it ended',
            type: 'string',
            enum: ['running', 'merging', 'decision', 'done']
        )]
        private string $state,
        #[Property(description: 'The rows the run imports', type: 'integer', example: 306)]
        private int $total,
        #[Property(description: 'The rows it has dealt with', type: 'integer', example: 184)]
        private int $processed,
        #[Property(description: 'The objects updated', type: 'integer', example: 296)]
        private int $updated,
        #[Property(description: 'The objects created', type: 'integer', example: 9)]
        private int $created,
        #[Property(description: 'The rows that could not be saved', type: 'integer', example: 4)]
        private int $failed,
        #[Property(description: 'The rows left out by the choice in the preview', type: 'integer', example: 3)]
        private int $skipped,
        #[Property(description: 'What the run does with rows with errors', type: 'string', enum: ['skip', 'values'])]
        private string $errorMode,
        #[Property(description: 'The name of the imported file', type: 'string', example: 'autumn.xlsx')]
        private string $fileName,
        #[Property(description: 'How the column holding the match value is called', type: 'string', example: 'SKU')]
        private string $matchColumn,
        #[Property(description: 'Path of the folder new objects go to', type: 'string', example: '/Products')]
        private string $newObjectsFolder,
        #[Property(description: 'ID of the folder new objects go to', type: 'integer', example: 7)]
        private int $newObjectsFolderId,
        #[Property(description: 'Who runs the import', type: 'string', example: 'Jo Doe')]
        private string $userName,
        #[Property(description: 'When the run ended, as a timestamp', type: 'integer', nullable: true)]
        private ?int $finishedAt,
        #[Property(description: 'Whether the rows go into a Change Control branch that is merged', type: 'boolean')]
        private bool $throughBranch,
        #[Property(description: 'The objects the merge covers', type: 'integer', example: 306)]
        private int $mergeTotal,
        #[Property(description: 'The objects the merge has written so far', type: 'integer', example: 184)]
        private int $mergeDone,
        #[Property(
            description: 'What went wrong that concerns no row of the file',
            type: 'string',
            nullable: true
        )]
        private ?string $notice,
        #[Property(
            description: 'The rows not saved, once the run has ended',
            type: 'array',
            items: new Items(
                required: ['row', 'matchValue', 'action', 'message'],
                properties: [
                    new Property(property: 'row', description: 'Number of the row in the file', type: 'integer'),
                    new Property(property: 'matchValue', description: 'The value matched by', type: 'string'),
                    new Property(property: 'action', type: 'string', enum: ['update', 'create'], nullable: true),
                    new Property(property: 'message', description: 'Why it was not saved', type: 'string'),
                ],
                type: 'object'
            )
        )]
        private array $failures,
    ) {
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getErrorMode(): string
    {
        return $this->errorMode;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getMatchColumn(): string
    {
        return $this->matchColumn;
    }

    public function getNewObjectsFolder(): string
    {
        return $this->newObjectsFolder;
    }

    public function getNewObjectsFolderId(): int
    {
        return $this->newObjectsFolderId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getFinishedAt(): ?int
    {
        return $this->finishedAt;
    }

    public function isThroughBranch(): bool
    {
        return $this->throughBranch;
    }

    public function getMergeTotal(): int
    {
        return $this->mergeTotal;
    }

    public function getMergeDone(): int
    {
        return $this->mergeDone;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    /**
     * @return list<Failure>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
