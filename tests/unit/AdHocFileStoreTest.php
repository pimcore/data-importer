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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;

class AdHocFileStoreTest extends Unit
{
    private const int USER_ID = 4712;

    private string $storage;

    protected function _before(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-store-', true);
        mkdir($this->storage);
    }

    protected function _after(): void
    {
        foreach (glob($this->storage . '/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->storage . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            @rmdir($directory);
        }
        @rmdir($this->storage);
    }

    public function testTheSkippedRowsOfAFileAreKeptForItsUserUntilTheFileIsDeleted(): void
    {
        $store = AdHocCsvFiles::store($this->storage);
        $fileId = $this->storeFile($store);

        $store->storeSkippedRows(self::USER_ID, $fileId, '{"rows":[1]}');
        $store->storeSkippedRows(self::USER_ID, $fileId, '{"rows":[2]}');

        $this->assertSame('{"rows":[2]}', $store->readSkippedRows(self::USER_ID, $fileId), 'the last dry run counts');
        $this->assertNull($store->readSkippedRows(self::USER_ID + 1, $fileId), 'another user has none');
        $this->assertSame(FileType::Csv, $store->findType(self::USER_ID, $fileId), 'they are no file of their own');

        $store->delete(self::USER_ID, $fileId, FileType::Csv);

        $this->assertNull($store->readSkippedRows(self::USER_ID, $fileId));
        $this->assertSame([], glob($this->storage . '/' . self::USER_ID . '/*'));
    }

    public function testAnEndedRunLeavesItsDownloadsWhenItsUploadIsDeleted(): void
    {
        $store = AdHocCsvFiles::store($this->storage);
        $fileId = $this->storeFile($store);
        $store->storeSkippedRows(self::USER_ID, $fileId, '{"skipped":1}');
        $store->storeFailedRows(self::USER_ID, $fileId, '{"failed":1}');

        $store->deleteUpload(self::USER_ID, $fileId, FileType::Csv);

        $this->assertNull($store->findType(self::USER_ID, $fileId));
        $this->assertSame('{"skipped":1}', $store->readSkippedRows(self::USER_ID, $fileId));
        $this->assertSame('{"failed":1}', $store->readFailedRows(self::USER_ID, $fileId));

        $store->deleteRunOutput(self::USER_ID, $fileId);

        $this->assertNull($store->readSkippedRows(self::USER_ID, $fileId));
        $this->assertNull($store->readFailedRows(self::USER_ID, $fileId));
    }

    public function testTheFailedRowsAreForTheirUserAndNamedOnlyByAFileId(): void
    {
        $store = AdHocCsvFiles::store($this->storage);
        $fileId = $this->storeFile($store);
        $store->storeFailedRows(self::USER_ID, $fileId, '{}');

        $this->assertNull($store->readFailedRows(self::USER_ID + 1, $fileId), 'another user has none');
        $this->assertNull($store->readFailedRows(self::USER_ID, '../' . $fileId));
    }

    public function testAFileWithoutADryRunIsDeletedAndHasNoSkippedRows(): void
    {
        $store = AdHocCsvFiles::store($this->storage);
        $fileId = $this->storeFile($store);

        $this->assertNull($store->readSkippedRows(self::USER_ID, $fileId));
        $this->assertNull($store->readSkippedRows(self::USER_ID, '../' . $fileId), 'only a file id names them');

        $store->delete(self::USER_ID, $fileId, FileType::Csv);
        $this->assertSame([], glob($this->storage . '/' . self::USER_ID . '/*'));
    }

    public function testAbandonedSkippedRowsAreRemovedWithTheirFile(): void
    {
        $store = AdHocCsvFiles::store($this->storage);
        $fileId = $this->storeFile($store);
        $store->storeSkippedRows(self::USER_ID, $fileId, '{}');
        foreach (glob($this->storage . '/' . self::USER_ID . '/*') ?: [] as $file) {
            touch($file, time() - 2 * 86400);
        }

        $next = $this->storeFile($store);

        $this->assertSame(
            [$this->storage . '/' . self::USER_ID . '/' . $next . '.csv'],
            glob($this->storage . '/' . self::USER_ID . '/*')
        );
    }

    public function testAbandonedFailedRowsAreRemovedOnTheNextUpload(): void
    {
        $store = AdHocCsvFiles::store($this->storage);
        $fileId = $this->storeFile($store);
        $store->storeSkippedRows(self::USER_ID, $fileId, '{"skipped":1}');
        $store->storeFailedRows(self::USER_ID, $fileId, '{"failed":1}');
        touch($this->storage . '/' . self::USER_ID . '/' . $fileId . '.failed.json', time() - 25 * 3600);

        $this->storeFile($store);

        $this->assertNull($store->readFailedRows(self::USER_ID, $fileId), 'file values do not stay on disk');
        $this->assertSame('{"skipped":1}', $store->readSkippedRows(self::USER_ID, $fileId), 'younger: it stays');
        $this->assertSame(FileType::Csv, $store->findType(self::USER_ID, $fileId), 'so does its upload');
    }

    public function testSkippedRowsNewerThanTheirAbandonedFileGoWithIt(): void
    {
        $store = AdHocCsvFiles::store($this->storage);
        $fileId = $this->storeFile($store);
        $store->storeSkippedRows(self::USER_ID, $fileId, '{}');
        touch($this->storage . '/' . self::USER_ID . '/' . $fileId . '.csv', time() - 25 * 3600);
        touch($this->storage . '/' . self::USER_ID . '/' . $fileId . '.skipped.json', time() - 3600);

        $next = $this->storeFile($store);

        $this->assertNull($store->readSkippedRows(self::USER_ID, $fileId));
        $this->assertSame(
            [$this->storage . '/' . self::USER_ID . '/' . $next . '.csv'],
            glob($this->storage . '/' . self::USER_ID . '/*')
        );
    }

    public function testADryRunThatEndsAfterItsFileWasDeletedKeepsNoSkippedRows(): void
    {
        $store = AdHocCsvFiles::store($this->storage);
        $fileId = $this->storeFile($store);
        $store->delete(self::USER_ID, $fileId, FileType::Csv);

        $store->storeSkippedRows(self::USER_ID, $fileId, '{"rows":[1]}');

        $this->assertNull($store->readSkippedRows(self::USER_ID, $fileId));
        $this->assertSame([], glob($this->storage . '/' . self::USER_ID . '/*'));
    }

    private function storeFile(FileStore $store): string
    {
        $path = $this->storage . '/upload.csv';
        file_put_contents($path, "sku\nA-1\n");
        $fileId = $store->store(self::USER_ID, FileType::Csv, $path);
        @unlink($path);

        return $fileId;
    }
}
