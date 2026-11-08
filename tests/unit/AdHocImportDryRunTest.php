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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunner;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\TransformationChain;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplicationScope;
use Pimcore\Bundle\DataImporterBundle\Mapping\Apply\MappingApplier;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatch;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatcherFactory;
use Pimcore\Bundle\DataImporterBundle\Resolver\Matching\RowMatchOutcome;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportConfigurationService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunService;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\MappingSuggestionServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Tests\Fixture\RowMatcherModel;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\DataObjectResolverInterface;
use Pimcore\Bundle\StudioBackendBundle\Class\Service\ClassDefinitionServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\InvalidArgumentException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\User;
use Pimcore\Security\User\User as SecurityUser;
use Pimcore\Tests\Support\Util\TestHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The dry run over real objects, as the container wires it, with the bundle's English texts.
 */
class AdHocImportDryRunTest extends Unit // NOSONAR php:S1200 one fixture for the dry run over real objects
{
    private const string CLASS_NAME = RowMatcherModel::CLASS_NAME;

    private const string MANDATORY_CLASS_NAME = 'DiDryRunMandatoryClass';

    private const string ROOT = '/di-dry-run';

    private const string TARGET = self::ROOT . '/target';

    private const string ELSEWHERE = self::ROOT . '/elsewhere';

    private const string NEW_FOLDER = self::ROOT . '/new';

    private const int USER_ID = 4721;

    private const int OTHER_USER_ID = 4722;

    // one row of each kind, the header row first
    private const string CSV = "sku;name;price;state\n"
        . "SKU-1;Armchair;2.50;red\n"
        . "SKU-NEW;Sofa;3;\n"
        . "SKU-1;Again;1;\n"
        . "SKU-2;Table;12,5,0;\n"
        . "SKU-3;Lamp;4;blue\n"
        . "SKU-TWICE;Shelf;1;\n"
        . "SKU-OUT;Desk;1;\n"
        . ";Nameless;1;\n"
        . "SKU-FOLDER;Box;1;\n";

    private const string ONE_ROW = "sku;name\nSKU-1;Chair\n";

    protected UnitTester $tester;

    private ClassDefinition $class;

    private int $folderId;

    /** @var list<string> */
    private array $files = [];

    /** @var list<array{int, string}> */
    private array $uploads = [];

    private User $user;

    protected function _before(): void
    {
        $this->class = ClassDefinition::getByName(self::CLASS_NAME) ?? RowMatcherModel::createClass();
        $this->folderId = (int) Service::createFolderByPath(self::TARGET)->getId();
        Service::createFolderByPath(self::NEW_FOLDER);
        $this->user = $this->logIn(self::USER_ID);
    }

    protected function _after(): void
    {
        foreach ($this->uploads as [$userId, $fileId]) {
            $store = $this->fileStore();
            $type = $store->findType($userId, $fileId);
            if ($type !== null) {
                $store->delete($userId, $fileId, $type);
            }
        }
        foreach ($this->files as $file) {
            @unlink($file);
        }
        $this->tokenStorage()->setToken(null);
        TestHelper::cleanUp();
    }

    /**
     * GL1: a CSV and an XLSX file into a folder, suggestions shown, a configuration selectable, the preview with every
     * kind of error row, and nothing written.
     */
    public function testAFileIsCheckedRowByRowWithEveryKindOfErrorRowAndNothingIsWritten(): void
    {
        $sku1 = $this->createObjects();
        $before = $this->snapshot();

        $fileId = $this->upload(self::CSV, 'products.csv');
        $suggested = $this->suggestions(['sku', 'name', 'price', 'state']);
        $this->assertSame('name', $suggested['name'] ?? null, 'a column is suggested its field');

        $response = $this->service()->dryRun($fileId, $this->mappedHere());

        $this->assertSame('sku', $response->getMatchColumn());
        $this->assertSame(self::TARGET, $response->getNewObjectsFolder());
        $this->assertSame(['name', 'price', 'state'], array_column($response->getFields(), 'key'));
        $rows = $this->byRow($response);
        $this->assertSame(range(2, 10), array_keys($rows));

        $this->assertSame('update', $rows[2]['action']);
        $this->assertNull($rows[2]['error']);
        $this->assertSame(['old' => 'Chair', 'new' => 'Armchair'], $rows[2]['values'][0]);
        $this->assertSame([], $rows[2]['invalid']);

        $this->assertSame('create', $rows[3]['action']);
        $this->assertSame(['old' => null, 'new' => 'Sofa'], $rows[3]['values'][0]);

        $this->assertSame(
            [4 => 'sku SKU-1 is in the file twice (row 2)',
                7 => '2 objects have sku SKU-TWICE',
                8 => 'Found in ' . self::ELSEWHERE . ', outside ' . self::TARGET,
                9 => 'sku is empty',
                10 => self::TARGET . ' already holds an object named SKU-FOLDER'],
            array_map(
                static fn (array $row): ?string => $row['error'],
                array_filter($rows, static fn (array $row): bool => $row['action'] === null)
            )
        );
        $this->assertSame(['old' => null, 'new' => 'Again'], $rows[4]['values'][0], 'the file\'s value');

        $this->assertSame('update', $rows[5]['action'], 'without the value it cannot take, the row is imported');
        $this->assertSame(
            [['field' => 1, 'label' => 'price', 'value' => '12,5,0', 'message' => 'price “12,5,0” is no number']],
            $rows[5]['invalid']
        );
        $this->assertSame(
            [['field' => 2, 'label' => 'state', 'value' => 'blue', 'message' => 'state “blue” is no option of state']],
            $rows[6]['invalid']
        );

        // the same check over an XLSX, matched by the Object ID and mapped with a configuration
        $xlsxId = $this->upload($this->xlsx([
            ['ID', 'Title'],
            [(string) $sku1->getId(), 'Stool'],
            ['999999999', 'Ghost'],
        ]), 'products.xlsx');
        $configurations = $this->configurationService([$this->idConfiguration()]);
        $listed = $configurations->listConfigurations()->getConfigurations();
        $this->assertSame(['by-id'], array_column($listed, 'name'));
        $this->assertNull($listed[0]['unusable'], 'the configuration can be chosen');

        $xlsxRows = $this->byRow($this->service($configurations)->dryRun($xlsxId, new AdHocImportDryRunParameters(
            $this->folderId,
            'ignored',
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            'ignored',
            configuration: 'by-id',
            configurationColumns: ['ID' => '0', 'Title' => '1'],
        )));
        $this->assertSame('update', $xlsxRows[2]['action']);
        $this->assertSame(['old' => 'Chair', 'new' => 'Stool'], $xlsxRows[2]['values'][0]);
        $this->assertSame('No object has the ID 999999999', $xlsxRows[3]['error']);

        $this->assertSame($before, $this->snapshot(), 'nothing is written');
    }

    public function testTheSkippedRowsAreKeptPerErrorModeForTheUploaderOnly(): void
    {
        $this->createObjects();
        $fileId = $this->upload(self::CSV, 'products.csv');
        $service = $this->service();
        $service->dryRun($fileId, $this->mappedHere());

        $skipped = $this->csvRows($service->skippedRows($fileId, AdHocImportDryRunServiceInterface::SKIP_ROWS));
        $this->assertSame(['Row', 'sku', 'name', 'price', 'state', 'Error'], $skipped[0]);
        $this->assertSame(['4', '5', '6', '7', '8', '9', '10'], array_column(array_slice($skipped, 1), 0));
        $this->assertSame(['5', 'SKU-2', 'Table', '12,5,0', '', 'price “12,5,0” is no number'], $skipped[2]);

        $unmatched = $this->csvRows(
            $service->skippedRows($fileId, AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES)
        );
        $this->assertSame(['4', '7', '8', '9', '10'], array_column(array_slice($unmatched, 1), 0));

        $this->logIn(self::OTHER_USER_ID);
        $this->expectException(NotFoundHttpException::class);
        $service->skippedRows($fileId, AdHocImportDryRunServiceInterface::SKIP_ROWS);
    }

    public function testAnotherErrorModeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->skippedRows('any', 'everything');
    }

    public function testAFileWithoutADryRunHasNoSkippedRows(): void
    {
        $fileId = $this->upload(self::ONE_ROW, 'products.csv');

        $this->expectException(NotFoundHttpException::class);

        $this->service()->skippedRows($fileId, AdHocImportDryRunServiceInterface::SKIP_ROWS);
    }

    public function testDeletingTheFileDeletesItsSkippedRows(): void
    {
        $fileId = $this->upload("sku;name\n;Chair\n", 'products.csv');
        $this->service()->dryRun($fileId, $this->mappedHere(['name' => 'name']));
        $this->assertNotNull($this->fileStore()->readSkippedRows(self::USER_ID, $fileId));

        $this->uploadService()->delete($fileId);

        $this->assertNull($this->fileStore()->readSkippedRows(self::USER_ID, $fileId));
    }

    public function testAnotherUsersFileIsNotFound(): void
    {
        $fileId = $this->upload(self::ONE_ROW, 'products.csv');
        $this->logIn(self::OTHER_USER_ID);

        $this->expectException(NotFoundHttpException::class);

        $this->service()->dryRun($fileId, $this->mappedHere(['name' => 'name']));
    }

    public function testAUserWhoMayNotCreateInTheFolderIsRefused(): void
    {
        $fileId = $this->upload(self::ONE_ROW, 'products.csv');
        $this->logIn(self::USER_ID, admin: false);

        $this->expectException(ForbiddenException::class);

        $this->service()->dryRun($fileId, $this->mappedHere(['name' => 'name']));
    }

    /**
     * @dataProvider mappingsThatCannotBeImported
     *
     * @param list<mixed> $columns
     */
    public function testAMappingThatCannotBeImportedIsRefused(array $columns, string $matchColumn = 'sku'): void
    {
        $fileId = $this->upload("sku;name;price\nSKU-1;Chair;1\n", 'products.csv');

        $this->expectException(InvalidArgumentException::class);

        $this->service()->dryRun($fileId, new AdHocImportDryRunParameters(
            $this->folderId,
            $this->class->getId(),
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            $matchColumn,
            'sku',
            columns: $columns
        ));
    }

    public function testMatchingByAFieldNeedsTheField(): void
    {
        $fileId = $this->upload(self::ONE_ROW, 'products.csv');

        $this->expectException(InvalidArgumentException::class);

        $this->service()->dryRun($fileId, new AdHocImportDryRunParameters(
            $this->folderId,
            $this->class->getId(),
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            'sku',
            columns: [['column' => 'name', 'field' => 'name']]
        ));
    }

    public static function mappingsThatCannotBeImported(): array
    {
        return [
            'a column the file lacks' => [[['column' => 'color', 'field' => 'name']]],
            'a match column the file lacks' => [[['column' => 'name', 'field' => 'name']], 'id'],
            'a field the class lacks' => [[['column' => 'name', 'field' => 'color']]],
            'the key, which names new objects' => [[['column' => 'name', 'field' => 'key']]],
            'a localized field without its language' => [[['column' => 'name', 'field' => 'title']]],
            'a language for a field that has none' => [[['column' => 'name', 'field' => 'name', 'language' => 'en']]],
            'two columns into one field' => [[
                ['column' => 'name', 'field' => 'name'],
                ['column' => 'price', 'field' => 'name'],
            ]],
            'a transformation an ad hoc import does not offer' => [[
                ['column' => 'name', 'field' => 'name', 'transformationPipeline' => [['type' => 'safeKey']]],
            ]],
            'a column that does not fit its field' => [[['column' => 'name', 'field' => 'relatedMany']]],
        ];
    }

    public function testALocalizedFieldIsWrittenInItsLanguage(): void
    {
        $this->createObject(self::TARGET, 'SKU-1', title: 'Old title');
        $fileId = $this->upload("sku;title\nSKU-1;New title\n", 'products.csv');

        $response = $this->service()->dryRun($fileId, new AdHocImportDryRunParameters(
            $this->folderId,
            $this->class->getId(),
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            'sku',
            'sku',
            columns: [['column' => 'title', 'field' => 'title', 'language' => 'en']]
        ));

        $this->assertSame([['key' => 'title', 'language' => 'en', 'label' => 'title']], $response->getFields());
        $this->assertSame(['old' => 'Old title', 'new' => 'New title'], $response->getRows()[0]['values'][0]);
    }

    public function testABrickFieldIsWrittenIntoItsBrick(): void
    {
        $this->createObject(self::TARGET, 'SKU-1');
        $fileId = $this->upload("sku;code\nSKU-1;C-9\n", 'products.csv');
        $brickField = 'bricks.' . RowMatcherModel::BRICK_NAME . '.code';

        $response = $this->service()->dryRun($fileId, $this->mappedHere(['code' => $brickField]));

        $this->assertSame([$brickField], array_column($response->getFields(), 'key'));
        $this->assertSame(['old' => null, 'new' => 'C-9'], $response->getRows()[0]['values'][0]);
        $this->assertSame([], $response->getRows()[0]['invalid']);
    }

    public function testValuesTheSaveWouldRefuseAreInvalidAndTheMatchFieldIsNotShown(): void
    {
        $this->createObject(self::TARGET, 'SKU-1');
        $fileId = $this->upload("sku;colors;notes\nSKU-1;red,blue;far too long a note\nSKU-2;red;fine\n", 'p.csv');

        $response = $this->service()->dryRun($fileId, $this->mappedHere([
            'sku' => 'sku',
            'colors' => ['field' => 'colors', 'transformationPipeline' => [
                ['type' => 'explode', 'settings' => ['delimiter' => ',']],
            ]],
            'notes' => 'notes',
        ]));

        $this->assertSame(['colors', 'notes'], array_column($response->getFields(), 'key'));
        [$changed, $created] = $response->getRows();
        $this->assertSame(
            ['colors “red,blue” is no option of colors', 'notes “far too long a note” does not fit the field'],
            array_column($changed['invalid'], 'message')
        );
        $this->assertSame('create', $created['action']);
        $this->assertSame([], $created['invalid']);
        $this->assertSame(['old' => null, 'new' => 'red'], $created['values'][0]);
    }

    public function testAPublishedObjectCannotLoseAMandatoryValueButANewOneMayLackIt(): void
    {
        $class = ClassDefinition::getByName(self::MANDATORY_CLASS_NAME) ?? $this->createMandatoryClass();
        $fqcn = '\\Pimcore\\Model\\DataObject\\' . self::MANDATORY_CLASS_NAME;
        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent(DataObject::getById($this->folderId));
        $object->setKey('mandatory');
        $object->setPublished(true);
        $object->set('sku', 'SKU-1');
        $object->set('name', 'Chair');
        $object->save();
        $fileId = $this->upload("sku;name\nSKU-1;\nSKU-2;\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, new AdHocImportDryRunParameters(
            $this->folderId,
            $class->getId(),
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            'sku',
            'sku',
            columns: [['column' => 'name', 'field' => 'name']]
        ))->getRows();

        $this->assertSame(['name is required'], array_column($rows[0]['invalid'], 'message'));
        $this->assertSame('create', $rows[1]['action']);
        $this->assertSame([], $rows[1]['invalid']);
    }

    public function testNewObjectsGoToTheFolderGivenWhichTheUserMustBeAllowedToCreateIn(): void
    {
        $fileId = $this->upload("sku;name\nSKU-9;Chair\n", 'products.csv');

        $response = $this->service()->dryRun($fileId, $this->mappedHere(['name' => 'name'], self::NEW_FOLDER));

        $this->assertSame(self::NEW_FOLDER, $response->getNewObjectsFolder());
        $this->assertSame('create', $response->getRows()[0]['action']);

        $this->expectException(NotFoundHttpException::class);
        $this->service()->dryRun($fileId, $this->mappedHere(['name' => 'name'], self::ROOT . '/missing'));
    }

    public function testWhereAnObjectTheUserMayNotSeeLiesIsNotTold(): void
    {
        $outside = $this->createObject(self::ELSEWHERE, 'SKU-OUT');
        $user = new User();
        $user->setId(self::OTHER_USER_ID);

        $message = $this->messages()->rowError(
            new RowMatch(RowMatchOutcome::OutsideTargetFolder, 'SKU-OUT', objectIds: [(int) $outside->getId()]),
            'sku',
            self::TARGET,
            self::TARGET,
            $user
        );

        $this->assertSame('Found outside ' . self::TARGET, $message);
    }

    /**
     * @return Concrete the object with SKU-1
     */
    private function createObjects(): Concrete
    {
        $first = $this->createObject(self::TARGET, 'SKU-1', 'Chair', '1.5');
        $this->createObject(self::TARGET, 'SKU-2', 'Table', '2');
        $this->createObject(self::TARGET, 'SKU-3', 'Lamp');
        $this->createObject(self::TARGET, 'SKU-TWICE');
        $this->createObject(self::TARGET . '/sub', 'SKU-TWICE');
        $this->createObject(self::ELSEWHERE, 'SKU-OUT');
        Service::createFolderByPath(self::TARGET . '/SKU-FOLDER');

        return $first;
    }

    // saved once: updating an object reaches the search index, which the test environment does not have
    private function createObject(
        string $folder,
        string $sku,
        ?string $name = null,
        ?string $price = null,
        ?string $title = null
    ): Concrete {
        $fqcn = '\\Pimcore\\Model\\DataObject\\' . self::CLASS_NAME;
        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent(Service::createFolderByPath($folder));
        $object->setKey(uniqid('o', true));
        $object->setPublished(true);
        $object->set('sku', $sku);
        $object->set('name', $name);
        $object->set('price', $price);
        $object->set('title', $title, 'en');
        $object->save();

        return $object;
    }

    private function createMandatoryClass(): ClassDefinition
    {
        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');
        foreach (['sku' => false, 'name' => true] as $field => $mandatory) {
            $input = new ClassDefinition\Data\Input();
            $input->setName($field);
            $input->setTitle($field);
            $input->setMandatory($mandatory);
            $panel->addChild($input);
        }

        $class = new ClassDefinition();
        $class->setName(self::MANDATORY_CLASS_NAME);
        $class->setLayoutDefinitions($panel);
        $class->save();

        return $class;
    }

    /**
     * Every object below the root folder with its modification date and name, read from the database.
     *
     * @return array<string, array{int|null, mixed}>
     */
    private function snapshot(): array
    {
        \Pimcore::collectGarbage();
        $snapshot = [];
        $listing = new DataObject\Listing();
        $listing->setUnpublished(true);
        foreach ($listing->load() as $object) {
            if (str_starts_with($object->getRealFullPath(), self::ROOT)) {
                $fresh = DataObject::getById($object->getId(), ['force' => true]);
                $snapshot[$object->getRealFullPath()] = [
                    $fresh?->getModificationDate(),
                    $fresh instanceof Concrete ? $fresh->get('name') : null,
                ];
            }
        }
        ksort($snapshot);

        return $snapshot;
    }

    /**
     * @param array<string, string|array{field: string, transformationPipeline?: list<mixed>}> $columns per column
     *                                                                                                  its field
     */
    private function mappedHere(
        array $columns = ['sku' => 'sku', 'name' => 'name', 'price' => 'price', 'state' => 'state'],
        ?string $newObjectsFolder = null
    ): AdHocImportDryRunParameters {
        $mapped = [];
        foreach ($columns as $column => $field) {
            $mapped[] = ['column' => $column, ...(is_array($field) ? $field : ['field' => $field])];
        }

        return new AdHocImportDryRunParameters(
            $this->folderId,
            $this->class->getId(),
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            'sku',
            'sku',
            newObjectsFolder: $newObjectsFolder,
            columns: $mapped
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function byRow(AdHocImportDryRunResponse $response): array
    {
        return array_column($response->getRows(), null, 'row');
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, string> per column the field suggested
     */
    private function suggestions(array $columns): array
    {
        /** @var MappingSuggestionServiceInterface $suggestions */
        $suggestions = $this->tester->grabService(MappingSuggestionServiceInterface::class);
        $suggested = $suggestions->suggest(new MappingSuggestionParameters(
            $this->class->getId(),
            array_map(static fn (string $column): array => ['index' => $column, 'label' => $column], $columns)
        ))->getSuggestions();

        return array_column($suggested, 'targetFieldName', 'sourceIndex');
    }

    private function upload(string $content, string $clientName): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('ad-hoc-dry-run-', true);
        file_put_contents($path, $content);
        $this->files[] = $path;

        $fileId = $this->uploadService()->upload(
            new UploadedFile($path, $clientName, null, null, true),
            $this->folderId
        )->getFileId();
        $this->assertNotNull($fileId);
        $this->uploads[] = [(int) $this->user->getId(), $fileId];

        return $fileId;
    }

    /**
     * @param list<list<string>> $rows
     */
    private function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = sys_get_temp_dir() . '/' . uniqid('ad-hoc-dry-run-', true) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        return (string) file_get_contents($path);
    }

    /**
     * @return list<list<string>>
     */
    private function csvRows(string $csv): array
    {
        $lines = array_filter(explode("\n", $csv), static fn (string $line): bool => $line !== '');

        return array_values(array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', ''),
            $lines
        ));
    }

    private function idConfiguration(): Configuration
    {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getName')->willReturn('by-id');
        $configuration->method('getType')->willReturn('dataImporterDataObject');
        $configuration->method('isAllowed')->willReturn(true);
        $configuration->method('getConfiguration')->willReturn([
            'interpreterConfig' => ['type' => 'xlsx', 'settings' => ['skipFirstRow' => true]],
            'resolverConfig' => [
                'dataObjectClassId' => $this->class->getId(),
                'loadingStrategy' => ['type' => 'id', 'settings' => ['dataSourceIndex' => 'ID']],
                'createLocationStrategy' => ['type' => 'staticPath', 'settings' => ['path' => self::TARGET]],
            ],
            'mappingConfig' => [[
                'label' => 'Title',
                'dataSourceIndex' => ['Title'],
                'transformationPipeline' => [],
                'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'name', 'language' => '']],
            ]],
        ]);

        return $configuration;
    }

    /**
     * @param list<Configuration> $configurations
     */
    private function configurationService(array $configurations = []): AdHocImportConfigurationService
    {
        return new AdHocImportConfigurationService(
            $this->tester->grabService(SecurityServiceInterface::class),
            $this->tester->grabService(ClassDefinitionServiceInterface::class),
            $this->tester->grabService(ClassDefinitionResolverInterface::class),
            $this->tester->grabService(DataObjectResolverInterface::class),
            $this->tester->grabService(RowMatcherFactory::class),
            static fn (): array => $configurations,
        );
    }

    private function service(?AdHocImportConfigurationService $configurations = null): AdHocImportDryRunService
    {
        return new AdHocImportDryRunService(
            $this->fileStore(),
            $this->tester->grabService(FileInspector::class),
            $this->tester->grabService(SecurityServiceInterface::class),
            $this->tester->grabService(ClassDefinitionServiceInterface::class),
            $this->tester->grabService(DataObjectResolverInterface::class),
            $this->tester->grabService(MappingSuggestionServiceInterface::class),
            $configurations ?? $this->configurationService(),
            $this->tester->grabService(TransformationChain::class),
            $this->tester->grabService(MappingApplier::class),
            $this->tester->grabService(RowMatcherFactory::class),
            new DryRunner($this->tester->grabService(MappingApplicationScope::class), $this->messages()),
            $this->messages()
        );
    }

    private function messages(): DryRunMessages
    {
        return AdHocDryRunMessagesTest::englishMessages();
    }

    private function uploadService(): AdHocImportServiceInterface
    {
        return $this->tester->grabService(AdHocImportServiceInterface::class);
    }

    private function fileStore(): FileStore
    {
        return $this->tester->grabService(FileStore::class);
    }

    private function logIn(int $id, bool $admin = true): User
    {
        $user = new User();
        $user->setId($id);
        $user->setName('ad-hoc-dry-run-' . $id);
        $user->setAdmin($admin);
        $securityUser = new SecurityUser($user);
        $this->tokenStorage()->setToken(
            new UsernamePasswordToken($securityUser, 'pimcore_studio', $securityUser->getRoles())
        );
        $this->user = $user;

        return $user;
    }

    private function tokenStorage(): TokenStorageInterface
    {
        return $this->tester->grabService('security.token_storage');
    }
}
