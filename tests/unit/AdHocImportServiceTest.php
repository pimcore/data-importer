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
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\DataTypeServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Schema\ClassDefinitionList;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Pimcore\Bundle\StudioBackendBundle\Response\ElementIcon;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\ElementIconTypes;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
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

    public function testASingleSheetWhoseFullGridIsTooLargeIsNotKept(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'sku')->setCellValue('XFD5001', 'far');
        $path = $this->xlsx(['Products' => [['sku']]]);
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->service()->upload($this->uploadOf($path, 'sparse.xlsx'), self::FOLDER_ID);

        $this->assertSame('too-many-cells', $response->getRefusal());
        $this->assertNull($response->getFileId());
        $this->assertSame([], $this->storedFiles());
    }

    public function testAWorkbookWithoutItsWorkbookPartIsNotKept(): void
    {
        $path = $this->xlsx(['Products' => [['sku'], ['A-1']]]);
        $zip = new \ZipArchive();
        $zip->open($path);
        $zip->deleteName('xl/workbook.xml');
        $zip->close();

        $response = $this->service()->upload($this->uploadOf($path, 'broken.xlsx'), self::FOLDER_ID);

        $this->assertSame('unreadable', $response->getRefusal());
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

        $this->service(folder: $this->createStub(Concrete::class))->upload(
            $this->upload(self::CSV, 'prices.csv'),
            self::FOLDER_ID
        );
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

    public function testTheFieldsOfAClassTheUserMayCreateAreAskedWithoutSystemOrAdvancedOnes(): void
    {
        $dataTypes = $this->createMock(DataTypeServiceInterface::class);
        $dataTypes->expects($this->once())->method('loadClassAttributes')->with(
            'PROD',
            [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::NUMERIC],
            false,
            false,
            false
        )->willReturn(new ClassAttributesResponse([['key' => 'sku', 'title' => 'SKU', 'localized' => false]]));

        $service = $this->service(dataTypeService: $dataTypes, creatableClasses: ['OTHER', 'PROD']);
        $fields = $service->loadMatchFields('PROD');

        $this->assertSame(['sku'], array_column($fields->getAttributes(), 'key'));
    }

    public function testTheFieldsOfAClassTheUserMayNotCreateAreForbidden(): void
    {
        $dataTypes = $this->createMock(DataTypeServiceInterface::class);
        $dataTypes->expects($this->never())->method('loadClassAttributes');

        $this->expectException(ForbiddenException::class);

        $this->service(dataTypeService: $dataTypes, creatableClasses: ['OTHER'])->loadMatchFields('PROD');
    }

    public function testTheObjectTypeOfAVariantClassIsNoFieldToMatchBy(): void
    {
        $answer = new ClassAttributesResponse([
            ['key' => 'sku', 'title' => 'SKU', 'localized' => false],
            ['key' => 'type', 'title' => 'SYSTEM Object Type ("variant"|"object")', 'localized' => false],
            ['key' => 'name', 'title' => 'Name', 'localized' => false],
        ]);
        $answer->addAdditionalAttribute('from', 'a listener');
        $dataTypes = $this->createStub(DataTypeServiceInterface::class);
        $dataTypes->method('loadClassAttributes')->willReturn($answer);

        $fields = $this->service(dataTypeService: $dataTypes, creatableClasses: ['PROD'])->loadMatchFields('PROD');

        $this->assertSame(['sku', 'name'], array_column($fields->getAttributes(), 'key'));
        $this->assertSame('a listener', $fields->getAdditionalAttribute('from'));
    }

    public function testTheMappingOffersTheClassFieldsSayingWhichTheClassRequires(): void
    {
        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturnMap([['PROD', [
            $this->target('sku'),
            $this->target('name', localized: true),
            $this->target('price', 'numeric'),
            $this->target('notes'),
        ]]]);
        $suggestions->method('suggest')->willReturn(new MappingSuggestionsResponse([]));

        $class = new ClassDefinition();
        $class->addFieldDefinition('sku', $this->input('sku', mandatory: true));
        $class->addFieldDefinition('price', $this->input('price', mandatory: false));
        $localized = new Localizedfields();
        $localized->setName('localizedfields');
        $localized->addChild($this->input('name', mandatory: true));
        $class->addFieldDefinition('localizedfields', $localized);

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions, class: $class)
            ->loadMapping(new MappingSuggestionParameters('PROD', [['index' => 'sku', 'label' => 'SKU']]));

        $this->assertSame([
            ['key' => 'sku', 'title' => 'sku', 'localized' => false, 'transformationResultType' => 'default', 'mandatory' => true],
            ['key' => 'name', 'title' => 'name', 'localized' => true, 'transformationResultType' => 'default', 'mandatory' => true],
            ['key' => 'price', 'title' => 'price', 'localized' => false, 'transformationResultType' => 'numeric', 'mandatory' => false],
            ['key' => 'notes', 'title' => 'notes', 'localized' => false, 'transformationResultType' => 'default', 'mandatory' => false],
        ], $mapping->getFields());
    }

    public function testTheMappingSuggestsForTheColumnsAsked(): void
    {
        $parameters = new MappingSuggestionParameters('PROD', [['index' => 'Bezeichnung DE', 'label' => 'Bezeichnung DE']]);
        $suggestion = [
            'sourceIndex' => 'Bezeichnung DE',
            'sourceLabel' => 'Bezeichnung DE',
            'targetFieldName' => 'bezeichnung',
            'targetFieldLabel' => 'Bezeichnung',
            'transformationResultType' => 'default',
            'score' => 100,
            'language' => 'de',
        ];
        $suggestions = $this->createMock(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([$this->target('bezeichnung', localized: true)]);
        $suggestions->expects($this->once())->method('suggest')->with($parameters)
            ->willReturn(new MappingSuggestionsResponse([$suggestion]));

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions)->loadMapping($parameters);

        $this->assertSame([$suggestion], $mapping->getSuggestions());
        // without the class definition nothing counts as required
        $this->assertFalse($mapping->getFields()[0]['mandatory']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fieldsAnAdHocImportDoesNotWrite(): iterable
    {
        yield 'the key, which new objects take from their match value' => ['key'];
        yield 'the path, as objects stay where they are found' => ['path'];
        yield 'the object type of a class with variants' => ['type'];
    }

    /**
     * @dataProvider fieldsAnAdHocImportDoesNotWrite
     */
    public function testTheMappingNeitherOffersNorSuggestsAField(string $key): void
    {
        $suggestions = $this->createStub(MappingSuggestionServiceInterface::class);
        $suggestions->method('loadTargets')->willReturn([$this->target($key), $this->target('name')]);
        $suggestions->method('suggest')->willReturn(new MappingSuggestionsResponse([
            $this->suggestion('Pfad', $key),
            $this->suggestion('Name', 'name'),
        ]));

        $mapping = $this->service(creatableClasses: ['PROD'], suggestions: $suggestions)
            ->loadMapping(new MappingSuggestionParameters('PROD', []));

        $this->assertSame(['name'], array_column($mapping->getFields(), 'key'));
        $this->assertSame(['name'], array_column($mapping->getSuggestions(), 'targetFieldName'));
    }

    public function testTheMappingOfAClassTheUserMayNotCreateIsForbidden(): void
    {
        $suggestions = $this->createMock(MappingSuggestionServiceInterface::class);
        $suggestions->expects($this->never())->method('loadTargets');
        $suggestions->expects($this->never())->method('suggest');

        $this->expectException(ForbiddenException::class);

        $this->service(creatableClasses: ['OTHER'], suggestions: $suggestions)
            ->loadMapping(new MappingSuggestionParameters('PROD', []));
    }

    /**
     * @return array{key: string, title: string, localized: bool, transformationResultType: string}
     */
    private function target(string $key, string $type = 'default', bool $localized = false): array
    {
        return ['key' => $key, 'title' => $key, 'localized' => $localized, 'transformationResultType' => $type];
    }

    /**
     * @return array{sourceIndex: string, sourceLabel: string, targetFieldName: string, targetFieldLabel: string, transformationResultType: string, score: int, language: null}
     */
    private function suggestion(string $column, string $field): array
    {
        return [
            'sourceIndex' => $column,
            'sourceLabel' => $column,
            'targetFieldName' => $field,
            'targetFieldLabel' => $field,
            'transformationResultType' => 'default',
            'score' => 100,
            'language' => null,
        ];
    }

    private function input(string $name, bool $mandatory): Input
    {
        $input = new Input();
        $input->setName($name);
        $input->setMandatory($mandatory);

        return $input;
    }

    /**
     * @param string[] $creatableClasses
     */
    private function service(
        mixed $folder = 'allowed',
        ?DataTypeServiceInterface $dataTypeService = null,
        array $creatableClasses = [],
        ?MappingSuggestionServiceInterface $suggestions = null,
        ?ClassDefinition $class = null
    ): AdHocImportService {
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

        $classes = $this->createStub(ClassDefinitionServiceInterface::class);
        $classes->method('getClassDefinitionCollection')->willReturnCallback(
            static fn (bool $creatableOnly = false): array => $creatableOnly ? array_map(
                static fn (string $id): ClassDefinitionList => new ClassDefinitionList(
                    $id,
                    $id,
                    $id,
                    new ElementIcon(ElementIconTypes::NAME->value, 'class')
                ),
                $creatableClasses
            ) : []
        );

        $classResolver = $this->createStub(ClassDefinitionResolverInterface::class);
        $classResolver->method('getById')->willReturn($class);

        return new AdHocImportService(
            new FileStore(new Filesystem(new LocalFilesystemAdapter($this->storage))),
            new FileInspector(new SourceFileReader($factory), $factory, new CsvDelimiterDetector()),
            $security,
            $resolver,
            $dataTypeService ?? $this->createStub(DataTypeServiceInterface::class),
            $classes,
            $suggestions ?? $this->createStub(MappingSuggestionServiceInterface::class),
            $classResolver
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
