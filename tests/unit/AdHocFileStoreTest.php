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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RunRecord;

class AdHocFileStoreTest extends Unit
{
    private const int USER_ID = 4712;

    private const string SKIPPED_ROWS = '{"skipped":1}';

    private const string FAILED_ROWS = '{"failed":1}';

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
        $store->storeSkippedRows(self::USER_ID, $fileId, self::SKIPPED_ROWS);
        $store->storeFailedRows(self::USER_ID, $fileId, self::FAILED_ROWS);

        $store->deleteUpload(self::USER_ID, $fileId, FileType::Csv);

        $this->assertNull($store->findType(self::USER_ID, $fileId));
        $this->assertSame(self::SKIPPED_ROWS, $store->readSkippedRows(self::USER_ID, $fileId));
        $this->assertSame(self::FAILED_ROWS, $store->readFailedRows(self::USER_ID, $fileId));

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
        $store->storeSkippedRows(self::USER_ID, $fileId, self::SKIPPED_ROWS);
        $store->storeFailedRows(self::USER_ID, $fileId, self::FAILED_ROWS);
        touch($this->storage . '/' . self::USER_ID . '/' . $fileId . '.failed.json', time() - 25 * 3600);

        $this->storeFile($store);

        $this->assertNull($store->readFailedRows(self::USER_ID, $fileId), 'file values do not stay on disk');
        $this->assertSame(self::SKIPPED_ROWS, $store->readSkippedRows(self::USER_ID, $fileId), 'younger: it stays');
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

    public function testAnUploadKeepsTheOldFilesOfARunThatHasNotEnded(): void
    {
        $runs = AdHocRunFixtures::memoryStore();
        $store = AdHocCsvFiles::store($this->storage, $runs);
        $waiting = $this->storeFile($store);
        $ended = $this->storeFile($store);
        $store->storeFailedRows(self::USER_ID, $waiting, self::FAILED_ROWS);
        $store->storeFailedRows(self::USER_ID, $ended, self::FAILED_ROWS);
        $runs->save(AdHocRunFixtures::run(['userId' => self::USER_ID, 'fileId' => $waiting])->waitingForDecision(1));
        $runs->save(AdHocRunFixtures::run(['userId' => self::USER_ID, 'fileId' => $ended])->finished(1));
        foreach (glob($this->storage . '/' . self::USER_ID . '/*') ?: [] as $file) {
            touch($file, time() - 2 * 86400);
        }

        $this->storeFile($store);

        $this->assertSame(FileType::Csv, $store->findType(self::USER_ID, $waiting), 'a run waiting for a decision');
        $this->assertSame(self::FAILED_ROWS, $store->readFailedRows(self::USER_ID, $waiting));
        $this->assertNull($store->findType(self::USER_ID, $ended), 'an ended run is abandoned like any file');
        $this->assertNull($store->readFailedRows(self::USER_ID, $ended));
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
