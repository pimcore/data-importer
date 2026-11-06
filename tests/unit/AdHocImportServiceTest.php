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
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\CsvDelimiterDetector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\CsvFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\XlsxFileInterpreter;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\SourceFileReader;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportService;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdHocImportServiceTest extends Unit
{
    private const FOLDER_ID = 77;

    private const USER_ID = 4712;

    private const OTHER_USER_ID = 4713;

    private const CSV = "sku;name\nA-1;Chair\n";

    private string $storage;

    private int $currentUserId = self::USER_ID;

    /**
     * @var string[]
     */
    private array $files = [];

    protected function _before(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . uniqid('ad-hoc-store-', true);
        mkdir($this->storage);
    }

    protected function _after(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        foreach ($this->storedFiles() as $file) {
            @unlink($file);
        }
        foreach (glob($this->storage . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            @rmdir($directory);
        }
        @rmdir($this->storage);
    }

    public function testAnUploadIsStoredForItsUserAndInspected(): void
    {
        $response = $this->service()->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID);

        $this->assertNull($response->getRefusal());
        $this->assertSame('csv', $response->getType());
        $this->assertSame(FileInspector::MAX_ROWS, $response->getMaxRows());
        $this->assertSame(';', $response->getDelimiter());
        $this->assertSame(';', $response->getDetectedDelimiter());
        $this->assertSame('"', $response->getEnclosure());
        $this->assertSame('\\', $response->getEscape());
        $this->assertTrue($response->getHasHeaderRow());
        $this->assertSame(1, $response->getRowCount());
        $this->assertSame(['sku', 'name'], array_column($response->getColumns(), 'label'));
        $this->assertSame([['A-1', 'Chair']], $response->getFirstRows());
        $this->assertFileExists($this->storage . '/' . self::USER_ID . '/' . $response->getFileId() . '.csv');
    }

    public function testAnotherExtensionIsTheWrongFormatAndNothingIsStored(): void
    {
        $response = $this->service()->upload($this->upload('anything', 'prices.pdf'), self::FOLDER_ID);

        $this->assertSame('wrong-format', $response->getRefusal());
        $this->assertNull($response->getFileId());
        $this->assertNull($response->getType());
        $this->assertSame([], $this->storedFiles());
    }

    public function testAFileThatIsNotOfItsTypeIsRefusedAndNotKept(): void
    {
        $response = $this->service()->upload($this->upload(self::CSV, 'prices.xlsx'), self::FOLDER_ID);

        $this->assertSame('unreadable', $response->getRefusal());
        $this->assertSame('xlsx', $response->getType());
        $this->assertNull($response->getFileId());
        $this->assertSame([], $this->storedFiles());
    }

    public function testAnEmptyFileIsRefusedAndNotKept(): void
    {
        $response = $this->service()->upload($this->upload('', 'prices.csv'), self::FOLDER_ID);

        $this->assertSame('unreadable', $response->getRefusal());
        $this->assertNull($response->getFileId());
        $this->assertSame([], $this->storedFiles());
    }

    public function testACsvOverTheRowLimitIsRefusedWithItsCountAndNotKept(): void
    {
        $response = $this->service()->upload(
            $this->upload("sku,name\n" . str_repeat("A-1,Chair\n", FileInspector::MAX_ROWS + 1), 'prices.csv'),
            self::FOLDER_ID
        );

        $this->assertSame('too-many-rows', $response->getRefusal());
        $this->assertSame(FileInspector::MAX_ROWS + 1, $response->getRowCount());
        $this->assertNull($response->getFileId());
        $this->assertSame([], $this->storedFiles());
    }

    public function testAWorkbookWithASingleSheetOverTheLimitIsNotKept(): void
    {
        $path = $this->xlsx(['Big' => array_fill(0, FileInspector::MAX_ROWS + 2, ['value'])]);

        $response = $this->service()->upload($this->uploadOf($path, 'big.xlsx'), self::FOLDER_ID);

        $this->assertSame('too-many-rows', $response->getRefusal());
        $this->assertNull($response->getFileId());
        $this->assertSame([], $this->storedFiles());
    }

    public function testAWorkbookWhoseFirstSheetIsOverTheLimitIsKeptSoAnotherSheetCanBeChosen(): void
    {
        $path = $this->xlsx([
            'Big' => array_fill(0, FileInspector::MAX_ROWS + 2, ['value']),
            'Small' => [['sku'], ['A-1']],
        ]);

        $response = $this->service()->upload($this->uploadOf($path, 'big.xlsx'), self::FOLDER_ID);

        $this->assertSame('too-many-rows', $response->getRefusal());
        $this->assertNotNull($response->getFileId());
        $this->assertSame(['Big', 'Small'], $response->getSheets());

        $small = $this->service()->inspect($response->getFileId(), new SettingsInput(sheetName: 'Small'));

        $this->assertNull($small->getRefusal());
        $this->assertSame('Small', $small->getSheetName());
        $this->assertSame(1, $small->getRowCount());
    }

    public function testACsvNotFittingItsHeaderIsKeptSoItsSettingsCanBeChanged(): void
    {
        $response = $this->service()->upload($this->upload("a;b\n1,2;3\n4\n", 'prices.csv'), self::FOLDER_ID);

        $this->assertSame('unreadable', $response->getRefusal());
        $this->assertNotNull($response->getFileId());

        $withoutHeader = $this->service()->inspect($response->getFileId(), new SettingsInput(hasHeaderRow: false));

        $this->assertNull($withoutHeader->getRefusal());
    }

    public function testSettingsThatRefuseAFileDoNotDeleteIt(): void
    {
        $fileId = $this->service()->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID)->getFileId();

        $refused = $this->service()->inspect($fileId, new SettingsInput(enclosure: '“'));

        $this->assertSame('unreadable', $refused->getRefusal());
        $this->assertSame($fileId, $refused->getFileId());
        $this->assertNull($this->service()->inspect($fileId, new SettingsInput())->getRefusal());
    }

    public function testAFolderThatDoesNotExistIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->service(folder: null)->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID);
    }

    public function testAnObjectIsNoFolderToImportInto(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->service(folder: $this->createStub(Concrete::class))->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID);
    }

    /**
     * @dataProvider missingFolderRights
     */
    public function testBothCreateAndSaveOnTheFolderAreNeededAndNothingIsStoredWithoutThem(string $missing): void
    {
        $folder = $this->createStub(Folder::class);
        $folder->method('isAllowed')->willReturnCallback(
            static fn (string $right): bool => $right !== $missing
        );

        try {
            $this->service(folder: $folder)->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID);
            $this->fail('the upload is refused');
        } catch (ForbiddenException) {
            $this->assertSame([], $this->storedFiles());
        }
    }

    public static function missingFolderRights(): iterable
    {
        yield 'create' => ['create'];
        yield 'save' => ['save'];
    }

    public function testTheFolderRightsAreCheckedForTheCurrentUser(): void
    {
        $folder = $this->createMock(Folder::class);
        $folder->expects($this->exactly(2))->method('isAllowed')->with(
            $this->anything(),
            $this->callback(static fn (User $user): bool => $user->getId() === self::USER_ID)
        )->willReturn(true);

        $this->service(folder: $folder)->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID);
    }

    public function testAFileOverTheSizeLimitIsRefusedBeforeItIsStored(): void
    {
        $upload = $this->createStub(UploadedFile::class);
        $upload->method('isValid')->willReturn(true);
        $upload->method('getSize')->willReturn(52428801);

        try {
            $this->service()->upload($upload, self::FOLDER_ID);
            $this->fail('the upload is refused');
        } catch (MaxFileSizeExceededException) {
            $this->assertSame([], $this->storedFiles());
        }
    }

    public function testAnUploadThatDidNotArriveIsAnError(): void
    {
        $upload = new UploadedFile(__FILE__, 'prices.csv', null, UPLOAD_ERR_INI_SIZE, true);

        $this->expectException(EnvironmentException::class);

        $this->service()->upload($upload, self::FOLDER_ID);
    }

    public function testAnotherUserCannotReadTheFile(): void
    {
        $fileId = $this->service()->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID)->getFileId();

        $this->currentUserId = self::OTHER_USER_ID;

        $this->expectException(NotFoundHttpException::class);

        $this->service()->inspect($fileId, new SettingsInput());
    }

    public function testAnotherUserCannotDeleteTheFile(): void
    {
        $fileId = $this->service()->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID)->getFileId();

        $this->currentUserId = self::OTHER_USER_ID;

        try {
            $this->service()->delete($fileId);
            $this->fail('the delete is refused');
        } catch (NotFoundHttpException) {
            $this->assertCount(1, $this->storedFiles());
        }
    }

    /**
     * @dataProvider idsThatAreNoFile
     */
    public function testAnIdThatIsNoUuidIsNotFound(string $fileId): void
    {
        $this->service()->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID);

        $this->expectException(NotFoundHttpException::class);

        $this->service()->inspect($fileId, new SettingsInput());
    }

    public static function idsThatAreNoFile(): iterable
    {
        yield 'a path' => ['../' . self::OTHER_USER_ID . '/prices'];
        yield 'empty' => [''];
        yield 'an unknown uuid' => ['0f8fad5b-d9cb-469f-a165-70867728950e'];
    }

    public function testDeleteRemovesTheFile(): void
    {
        $fileId = $this->service()->upload($this->upload(self::CSV, 'prices.csv'), self::FOLDER_ID)->getFileId();

        $this->service()->delete($fileId);

        $this->assertSame([], $this->storedFiles());
    }

    public function testAnUploadRemovesTheFilesAUserAbandonedOnly(): void
    {
        $service = $this->service();
        $abandoned = $service->upload($this->upload(self::CSV, 'old.csv'), self::FOLDER_ID)->getFileId();
        $recent = $service->upload($this->upload(self::CSV, 'recent.csv'), self::FOLDER_ID)->getFileId();
        $this->currentUserId = self::OTHER_USER_ID;
        $foreign = $service->upload($this->upload(self::CSV, 'foreign.csv'), self::FOLDER_ID)->getFileId();

        touch($this->storage . '/' . self::USER_ID . '/' . $abandoned . '.csv', time() - 90000);
        touch($this->storage . '/' . self::OTHER_USER_ID . '/' . $foreign . '.csv', time() - 90000);

        $this->currentUserId = self::USER_ID;
        $new = $service->upload($this->upload(self::CSV, 'new.csv'), self::FOLDER_ID)->getFileId();

        $this->assertFileDoesNotExist($this->storage . '/' . self::USER_ID . '/' . $abandoned . '.csv');
        $this->assertFileExists($this->storage . '/' . self::USER_ID . '/' . $recent . '.csv');
        $this->assertFileExists($this->storage . '/' . self::USER_ID . '/' . $new . '.csv');
        $this->assertFileExists($this->storage . '/' . self::OTHER_USER_ID . '/' . $foreign . '.csv');
    }

    private function service(mixed $folder = 'allowed'): AdHocImportService
    {
        if ($folder === 'allowed') {
            $folder = $this->createStub(Folder::class);
            $folder->method('isAllowed')->willReturn(true);
        }

        $resolver = $this->createStub(DataObjectResolverInterface::class);
        $resolver->method('getById')->willReturnCallback(
            static fn (int $id): mixed => $id === self::FOLDER_ID ? $folder : null
        );

        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturnCallback(function (): User {
            $user = new User();
            $user->setId($this->currentUserId);

            return $user;
        });

        // QueueService and DeltaChecker are final and need the database, which reading rows does not touch
        $factory = new InterpreterFactory([
            'csv' => (new \ReflectionClass(CsvFileInterpreter::class))->newInstanceWithoutConstructor(),
            'xlsx' => (new \ReflectionClass(XlsxFileInterpreter::class))->newInstanceWithoutConstructor(),
        ]);

        return new AdHocImportService(
            new FileStore(new Filesystem(new LocalFilesystemAdapter($this->storage))),
            new FileInspector(new SourceFileReader($factory), $factory, new CsvDelimiterDetector()),
            $security,
            $resolver
        );
    }

    private function upload(string $content, string $clientName): UploadedFile
    {
        $path = sys_get_temp_dir() . '/' . uniqid('ad-hoc-upload-', true);
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $this->uploadOf($path, $clientName);
    }

    private function uploadOf(string $path, string $clientName): UploadedFile
    {
        return new UploadedFile($path, $clientName, null, null, true);
    }

    /**
     * @param array<string, array<int, array<int, mixed>>> $sheets
     */
    private function xlsx(array $sheets): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        foreach ($sheets as $name => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray($rows);
        }

        $path = sys_get_temp_dir() . '/' . uniqid('ad-hoc-upload-', true) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        return $path;
    }

    /**
     * @return string[]
     */
    private function storedFiles(): array
    {
        return array_values(array_filter(glob($this->storage . '/*/*') ?: [], 'is_file'));
    }
}
