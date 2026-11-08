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

namespace Pimcore\Bundle\DataImporterBundle\AdHocImport;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Pimcore\Helper\TemporaryFileHelperTrait;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Uploaded files until their import ran, and the rows a dry run would skip, each reachable only by the user who
 * uploaded it.
 *
 * @internal
 */
final class FileStore
{
    use TemporaryFileHelperTrait;

    // a dialog closed without its Cancel (browser closed, session ended) leaves its file behind
    private const int ABANDONED_AFTER_SECONDS = 86400;

    private const string SKIPPED_ROWS_SUFFIX = '.skipped.json';

    public function __construct(
        private readonly FilesystemOperator $pimcoreDataImporterAdHocStorage,
        private readonly LockFactory $lockFactory,
    ) {
    }

    /**
     * @return string the id of the stored file
     */
    public function store(int $userId, FileType $type, string $sourcePath): string
    {
        $this->removeAbandoned($userId);

        $fileId = Uuid::v4()->toRfc4122();
        $source = fopen($sourcePath, 'rb');

        try {
            $this->pimcoreDataImporterAdHocStorage->writeStream($this->path($userId, $fileId, $type), $source);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }

        return $fileId;
    }

    public function findType(int $userId, string $fileId): ?FileType
    {
        if (!Uuid::isValid($fileId)) {
            return null;
        }

        foreach (FileType::cases() as $type) {
            if ($this->pimcoreDataImporterAdHocStorage->fileExists($this->path($userId, $fileId, $type))) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return string a local path with the file's extension, which the spreadsheet reader goes by
     */
    public function localPath(int $userId, string $fileId, FileType $type): string
    {
        return self::getLocalFileFromStream(
            $this->pimcoreDataImporterAdHocStorage->readStream($this->path($userId, $fileId, $type))
        );
    }

    /**
     * Deletes the file and the skipped rows of its last dry run.
     */
    public function delete(int $userId, string $fileId, FileType $type): void
    {
        $lock = $this->lock($userId, $fileId);

        try {
            $this->pimcoreDataImporterAdHocStorage->delete($this->path($userId, $fileId, $type));
            $this->pimcoreDataImporterAdHocStorage->delete($this->skippedRowsPath($userId, $fileId));
        } finally {
            $lock->release();
        }
    }

    /**
     * Keeps the rows the last dry run of a file would skip, replacing those of an earlier one, until the file is
     * deleted or abandoned. A dry run still going when its file is deleted keeps nothing.
     */
    public function storeSkippedRows(int $userId, string $fileId, string $content): void
    {
        $lock = $this->lock($userId, $fileId);

        try {
            if ($this->findType($userId, $fileId) !== null) {
                $this->pimcoreDataImporterAdHocStorage->write($this->skippedRowsPath($userId, $fileId), $content);
            }
        } finally {
            $lock->release();
        }
    }

    public function readSkippedRows(int $userId, string $fileId): ?string
    {
        $path = $this->skippedRowsPath($userId, $fileId);
        if (!Uuid::isValid($fileId) || !$this->pimcoreDataImporterAdHocStorage->fileExists($path)) {
            return null;
        }

        return $this->pimcoreDataImporterAdHocStorage->read($path);
    }

    private function removeAbandoned(int $userId): void
    {
        $oldest = time() - self::ABANDONED_AFTER_SECONDS;

        /** @var list<StorageAttributes> $items */
        $items = $this->pimcoreDataImporterAdHocStorage->listContents((string)$userId)->toArray();
        foreach ($items as $item) {
            if ($item->isFile() && $item->lastModified() !== null && $item->lastModified() < $oldest) {
                $this->removeWithItsSkippedRows($item->path());
            }
        }
    }

    /**
     * A later dry run makes the skipped rows younger than the upload they go with.
     */
    private function removeWithItsSkippedRows(string $path): void
    {
        $this->pimcoreDataImporterAdHocStorage->delete($path);
        if (!str_ends_with($path, self::SKIPPED_ROWS_SUFFIX)) {
            $this->pimcoreDataImporterAdHocStorage->delete(
                (string) preg_replace('/\.[^.\/]+$/', self::SKIPPED_ROWS_SUFFIX, $path)
            );
        }
    }

    private function lock(int $userId, string $fileId): LockInterface
    {
        $lock = $this->lockFactory->createLock('data_importer_ad_hoc_file_' . $userId . '_' . $fileId);
        $lock->acquire(true);

        return $lock;
    }

    private function path(int $userId, string $fileId, FileType $type): string
    {
        return $userId . '/' . $fileId . '.' . $type->value;
    }

    private function skippedRowsPath(int $userId, string $fileId): string
    {
        return $userId . '/' . $fileId . self::SKIPPED_ROWS_SUFFIX;
    }
}
