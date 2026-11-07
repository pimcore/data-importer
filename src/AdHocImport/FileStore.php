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

    public function __construct(
        private readonly FilesystemOperator $pimcoreDataImporterAdHocStorage,
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
        $this->pimcoreDataImporterAdHocStorage->delete($this->path($userId, $fileId, $type));
        $this->pimcoreDataImporterAdHocStorage->delete($this->skippedRowsPath($userId, $fileId));
    }

    /**
     * Keeps the rows the last dry run of a file would skip, replacing those of an earlier one, until the file is
     * deleted or abandoned.
     */
    public function storeSkippedRows(int $userId, string $fileId, string $content): void
    {
        $this->pimcoreDataImporterAdHocStorage->write($this->skippedRowsPath($userId, $fileId), $content);
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

        /** @var StorageAttributes $item */
        foreach ($this->pimcoreDataImporterAdHocStorage->listContents((string)$userId) as $item) {
            if ($item->isFile() && $item->lastModified() !== null && $item->lastModified() < $oldest) {
                $this->pimcoreDataImporterAdHocStorage->delete($item->path());
            }
        }
    }

    private function path(int $userId, string $fileId, FileType $type): string
    {
        return $userId . '/' . $fileId . '.' . $type->value;
    }

    private function skippedRowsPath(int $userId, string $fileId): string
    {
        return $userId . '/' . $fileId . '.skipped.json';
    }
}
