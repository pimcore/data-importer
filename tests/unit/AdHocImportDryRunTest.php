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

use Carbon\Carbon;
use Codeception\Test\Unit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunMessages;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\DryRun\DryRunner;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileInspector;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileStore;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\FileType;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\ImportPlan;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowImporter;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\Run\RowWriterInterface;
use Pimcore\Bundle\DataImporterBundle\AdHocImport\StoreKey;
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
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\CountryOptionsProvider;
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace\DataObject as ObjectWorkspace;
use Pimcore\Security\User\User as SecurityUser;
use Pimcore\Tests\Support\Util\TestHelper;
use Pimcore\Tool;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
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

    private const string HEIGHT_VALUE = '30 cm';

    private const string FILE_WITHOUT_WEIGHT = "sku;weight\nSKU-1;\n";

    private const string OBJECT_NAMESPACE = '\\Pimcore\\Model\\DataObject\\';

    private const string MANDATORY_CLASS_NAME = 'DiDryRunMandatoryClass';

    private const string OPTIONS_CLASS_NAME = 'DiDryRunOptionsClass';

    private const string DATE_TIME_CLASS_NAME = 'DiDryRunDateTimeClass';

    private const string STORE_CLASS_NAME = 'DiDryRunStoreClass';

    private const string STORE_NAME = 'di-dry-run-store';

    private const string ROOT = '/di-dry-run';

    private const string TARGET = self::ROOT . '/target';

    private const string ELSEWHERE = self::ROOT . '/elsewhere';

    private const string NEW_FOLDER = self::ROOT . '/new';

    private const string HIDDEN_FOLDER = self::TARGET . '/hidden';

    private const string CLOSED_FOLDER = self::ROOT . '/closed';

    private const string FOUND_OUTSIDE_TARGET = 'Found outside ' . self::TARGET;

    private const string SECRET_NAME = 'Secret name';

    private const string CLOSED_NAME = 'Closed name';

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

    /** @var list<User> */
    private array $users = [];

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
        foreach ($this->users as $user) {
            $user->delete();
        }
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

    // the largest file the import takes is answered in one dry run, every row of it, within a bounded memory
    public function testAFileOfTheMostRowsTakenIsCheckedInFull(): void
    {
        $this->createObjects();
        $before = $this->snapshot();
        $csv = "sku;name;price\nSKU-1;Armchair;2.50\n";
        for ($row = 3; $row <= FileInspector::MAX_ROWS + 1; $row++) {
            $csv .= sprintf("BULK-%d;Item %d;%s\n", $row, $row, $row % 1000 === 0 ? 'many' : '1.25');
        }
        $fileId = $this->upload($csv, 'products.csv');

        memory_reset_peak_usage();
        $baseline = memory_get_usage();
        $rows = $this->byRow($this->service()->dryRun(
            $fileId,
            $this->mappedHere(['sku' => 'sku', 'name' => 'name', 'price' => 'price'])
        ));
        $peak = memory_get_peak_usage() - $baseline;

        $this->assertCount(FileInspector::MAX_ROWS, $rows);
        $this->assertSame('update', $rows[2]['action']);
        $this->assertSame(
            [FileInspector::MAX_ROWS - 1, 0],
            [
                count(array_filter($rows, static fn (array $row): bool => $row['action'] === 'create')),
                count(array_filter($rows, static fn (array $row): bool => $row['error'] !== null)),
            ]
        );
        $this->assertSame(
            [1000, 2000, 3000, 4000, 5000],
            array_keys(array_filter($rows, static fn (array $row): bool => $row['invalid'] !== []))
        );
        $this->assertLessThan(256 * 1024 * 1024, $peak, sprintf('peak %.1f MB', $peak / 1024 / 1024));
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
        $answers = [];
        foreach ([
            AdHocImportDryRunServiceInterface::SKIP_ROWS,
            AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES,
        ] as $errorMode) {
            try {
                $answers[$errorMode] = $service->skippedRows($fileId, $errorMode);
            } catch (NotFoundHttpException) {
                $answers[$errorMode] = 404;
            }
        }
        $this->assertSame(['skip' => 404, 'values' => 404], $answers);
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
        $fqcn = self::OBJECT_NAMESPACE . self::MANDATORY_CLASS_NAME;
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

    public function testAClassificationStoreKeyIsWrittenIntoThatKey(): void
    {
        [$class, $weight, $height] = $this->storeClass();
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->save();
        $fileId = $this->upload("sku;weight;height\nSKU-1;2 kg;30 cm\nSKU-2;5 kg;\n", 'products.csv');

        $response = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id()],
            ['column' => 'height', 'field' => 'attributes', 'key' => $height->id()],
        ]));

        $this->assertSame([
            ['key' => 'attributes', 'language' => null, 'label' => 'Basics › weight'],
            ['key' => 'attributes', 'language' => null, 'label' => 'Basics › height'],
        ], $response->getFields());
        [$changed, $created] = $response->getRows();
        $this->assertSame(
            [['old' => '1 kg', 'new' => '2 kg'], ['old' => null, 'new' => self::HEIGHT_VALUE]],
            $changed['values']
        );
        $this->assertSame([], $changed['invalid']);
        $this->assertSame('create', $created['action']);
        $this->assertSame([['old' => null, 'new' => '5 kg'], ['old' => null, 'new' => null]], $created['values']);
        $reloaded = DataObject::getById($object->getId(), ['force' => true]);
        $this->assertInstanceOf(Concrete::class, $reloaded);
        $this->assertSame(
            '1 kg',
            $reloaded->get('attributes')->getLocalizedKeyValue($weight->groupId, $weight->keyId),
            'the dry run writes nothing'
        );
    }

    public function testALocalizedStoreKeyIsWrittenInItsLanguageAndTheDefaultApart(): void
    {
        [$class, $weight] = $this->storeClass('Localized', localized: true);
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg en', 'en');
        $object->save();
        $fileId = $this->upload("sku;weight;base\nSKU-1;2 kg en;3 kg\n", 'products.csv');

        $response = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id(), 'language' => 'en'],
            ['column' => 'base', 'field' => 'attributes', 'key' => $weight->id()],
        ]));

        $this->assertSame(['en', null], array_column($response->getFields(), 'language'));
        $row = $response->getRows()[0];
        $this->assertSame(
            [['old' => '1 kg en', 'new' => '2 kg en'], ['old' => '1 kg', 'new' => '3 kg']],
            $row['values'],
            'the language and the default are read and written apart'
        );
        $this->assertSame([], $row['invalid']);
    }

    public function testAKeyMarkedMandatoryOnlyInItsOwnDefinitionMaySaveEmpty(): void
    {
        [$class, $weight] = $this->storeClass('Mandatory', mandatory: true);
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->save();
        $fileId = $this->upload(self::FILE_WITHOUT_WEIGHT, 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id()],
        ]))->getRows();

        $this->assertSame([], $rows[0]['invalid'], 'Pimcore checks the key in the group by the mark of the group');
    }

    public function testAPublishedObjectCannotLoseAStoreKeyItsGroupMarksMandatory(): void
    {
        [$class, $weight, $height] = $this->storeClass('MandatoryInGroup', mandatoryInGroup: true);
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->get('attributes')->setLocalizedKeyValue($height->groupId, $height->keyId, self::HEIGHT_VALUE);
        $object->save();
        $fileId = $this->upload(self::FILE_WITHOUT_WEIGHT, 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id()],
        ]))->getRows();

        $this->assertSame(['Basics › weight is required'], array_column($rows[0]['invalid'], 'message'));
        $this->assertSame([0], array_column($rows[0]['invalid'], 'field'), 'named by its column');
        $this->assertFalse($rows[0]['blocked'], 'left out, the key keeps its value');
    }

    public function testAStoreKeyLiftedByLeavingItOutIsAmongTheRowsLeftOutWriters(): void
    {
        [$class, $weight, $height] = $this->storeClass('LiftedForTheRun', mandatoryInGroup: true);
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->get('attributes')->setLocalizedKeyValue($height->groupId, $height->keyId, self::HEIGHT_VALUE);
        $object->save();
        $fileId = $this->upload(self::FILE_WITHOUT_WEIGHT, 'products.csv');

        $row = $this->service()->check($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id()],
        ]), $this->user)->results[0];

        $this->assertFalse($row->blocked);
        $this->assertTrue($row->importsWithoutInvalidValues());
        $this->assertSame([0], $row->leftOut, 'else the run writes the empty value the preview promised to leave out');
    }

    public function testActivatingAGroupRefusesTheMandatoryKeysTheFileDoesNotName(): void
    {
        [$class, $weight] = $this->storeClass('MandatoryInGroup', mandatoryInGroup: true);
        $object = $this->storeObject($class, 'SKU-1');
        $object->save();
        $fileId = $this->upload("sku;weight\nSKU-1;5 kg\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id()],
        ]))->getRows();

        $this->assertSame(['Basics › height is required'], array_column($rows[0]['invalid'], 'message'));
        $this->assertSame([null], array_column($rows[0]['invalid'], 'field'), 'the file has no column for it');
        $this->assertTrue($rows[0]['blocked'], 'no value of the file to leave out');
        $this->assertSame(['2'], array_column(array_slice($this->csvRows(
            $this->service()->skippedRows($fileId, AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES)
        ), 1), 0));

        $reloaded = DataObject::getById($object->getId(), ['force' => true]);
        $this->assertInstanceOf(Concrete::class, $reloaded);
        $reloaded->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '5 kg');
        $this->expectException(ValidationException::class);
        $reloaded->save();
    }

    public function testAnEmptyLanguageOfAGroupMandatoryKeyFollowsTheDefaultLanguageInAnInheritingClass(): void
    {
        [$class, $weight, $height] = $this->storeClass(
            'MandatoryInheriting',
            localized: true,
            mandatoryInGroup: true,
            allowInherit: true
        );
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->get('attributes')->setLocalizedKeyValue($height->groupId, $height->keyId, self::HEIGHT_VALUE);
        $object->save();
        $fileId = $this->upload(self::FILE_WITHOUT_WEIGHT, 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id(), 'language' => 'en'],
        ]))->getRows();

        $this->assertSame([], $rows[0]['invalid'], 'the save takes the value of the default language');
    }

    public function testAGroupMandatoryKeyWrittenInOneLanguageIsRefusedForTheOthers(): void
    {
        [$class, $weight, $height] = $this->storeClass('MandatoryLocalized', localized: true, mandatoryInGroup: true);
        $object = $this->storeObject($class, 'SKU-1');
        $object->save();
        $fileId = $this->upload("sku;weight;height\nSKU-1;5 kg;30 cm\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id(), 'language' => 'en'],
            ['column' => 'height', 'field' => 'attributes', 'key' => $height->id(), 'language' => 'en'],
        ]))->getRows();

        $this->assertEqualsCanonicalizing(
            ['Basics › weight (default, de) is required', 'Basics › height (default, de) is required'],
            array_column($rows[0]['invalid'], 'message'),
            'written in en only'
        );
        $this->assertSame([null, null], array_column($rows[0]['invalid'], 'field'));
        $this->assertTrue($rows[0]['blocked'], 'the other languages stay empty');
    }

    public function testAStoreKeyItsColumnReportedIsNotReportedAgainByItsGroup(): void
    {
        [$class, $weight] = $this->storeClass('MandatoryQuantity');
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->get('attributes')->setActiveGroups([$weight->groupId => true]);
        $object->save();
        $length = $this->quantityKey('MandatoryQuantity', mandatoryInGroup: true);
        $fileId = $this->upload("sku;length\nSKU-1;3 m\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'length', 'field' => 'attributes', 'key' => $length->id()],
        ]))->getRows();

        $this->assertSame(
            ['Basics › length “3 m” does not fit the field'],
            array_column($rows[0]['invalid'], 'message')
        );
        $this->assertTrue($rows[0]['blocked'], 'left out, the key stays empty in an active group');
    }

    public function testAStoreHoldingMoreGroupsThanItAllowsRefusesTheRow(): void
    {
        [$class, $weight] = $this->storeClass('OneGroup', maxItems: 1);
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->get('attributes')->setActiveGroups([$weight->groupId => true]);
        $object->save();
        $width = $this->keyOfAnotherGroup('OneGroup');
        $fileId = $this->upload("sku;width\nSKU-1;2 m\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'width', 'field' => 'attributes', 'key' => $width->id()],
        ]))->getRows();

        $this->assertSame('update', $rows[0]['action']);
        $this->assertSame(
            ['Attributes holds more groups than it allows'],
            array_column($rows[0]['invalid'], 'message')
        );
        $this->assertSame([null], array_column($rows[0]['invalid'], 'field'));
        $this->assertTrue($rows[0]['blocked']);

        $reloaded = DataObject::getById($object->getId(), ['force' => true]);
        $this->assertInstanceOf(Concrete::class, $reloaded);
        $reloaded->get('attributes')->setLocalizedKeyValue($width->groupId, $width->keyId, '2 m');
        $reloaded->get('attributes')->setActiveGroups([$weight->groupId => true, $width->groupId => true]);
        $this->expectException(ValidationException::class);
        $reloaded->save();
    }

    public function testAnOptionalStoreKeyEmptyBeforeOnlyLosesItsInvalidValue(): void
    {
        [$class] = $this->storeClass('OptionalKey');
        $width = $this->keyOfAnotherGroup('OptionalKey', '^\d+$');
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setActiveGroups([$width->groupId => true]);
        $object->save();
        $fileId = $this->upload("sku;width\nSKU-1;wide\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'width', 'field' => 'attributes', 'key' => $width->id()],
        ]))->getRows();

        $this->assertSame(['Dimensions › width “wide” does not fit the field'], array_column(
            $rows[0]['invalid'],
            'message'
        ));
        $this->assertFalse($rows[0]['blocked'], 'left out, the optional key stays empty, which a save accepts');
        $this->assertSame([], array_slice($this->csvRows(
            $this->service()->skippedRows($fileId, AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES)
        ), 1));
    }

    public function testAGroupOnlyAnInvalidValueWouldActivateDoesNotRefuseTheRow(): void
    {
        [$class, $weight] = $this->storeClass('OneGroupInvalid', maxItems: 1);
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->get('attributes')->setActiveGroups([$weight->groupId => true]);
        $object->save();
        $width = $this->keyOfAnotherGroup('OneGroupInvalid', '^\d+$');
        $fileId = $this->upload("sku;width\nSKU-1;wide\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'width', 'field' => 'attributes', 'key' => $width->id()],
        ]))->getRows();

        $this->assertSame(['Dimensions › width “wide” does not fit the field'], array_column(
            $rows[0]['invalid'],
            'message'
        ));
        $this->assertFalse($rows[0]['blocked'], 'without the value the second group is never activated');
    }

    public function testAStoreKeyOfATypeNoColumnLeadsToTakesNoValue(): void
    {
        [$class] = $this->storeClass('Quantity');
        $length = $this->quantityKey('Quantity');
        $fileId = $this->upload("sku;length\nSKU-1;3 m\nSKU-2;\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'length', 'field' => 'attributes', 'key' => $length->id()],
        ]))->getRows();

        $this->assertCount(2, $rows, 'the dry run answers instead of refusing the mapping');
        $this->assertSame(
            ['Basics › length “3 m” does not fit the field'],
            array_column($rows[0]['invalid'], 'message')
        );
        $this->assertSame([], $rows[1]['invalid'], 'an empty cell is no error');
    }

    /**
     * @dataProvider storeKeysThatCannotBeImported
     */
    public function testAStoreKeyThatCannotBeImportedIsRefused(string $case): void
    {
        [$class, $weight] = $this->storeClass();
        $fileId = $this->upload("sku;weight;more\nSKU-1;2 kg;3 kg\n", 'products.csv');
        $columns = match ($case) {
            'no key of the store' => [['column' => 'weight', 'field' => 'attributes', 'key' => '999999-999999']],
            'a key of another store' => [
                ['column' => 'weight', 'field' => 'attributes', 'key' => $this->storeClass('Other')[1]->id()],
            ],
            'a key without its group' => [
                ['column' => 'weight', 'field' => 'attributes', 'key' => (string) $weight->keyId],
            ],
            'a key of a field that is no store' => [['column' => 'weight', 'field' => 'sku', 'key' => $weight->id()]],
            'a language for a store that has none' => [
                ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id(), 'language' => 'en'],
            ],
            'two columns into one key' => [
                ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id()],
                ['column' => 'more', 'field' => 'attributes', 'key' => $weight->id()],
            ],
            default => [['column' => 'weight', 'field' => 'attributes']],
        };

        $this->expectException(InvalidArgumentException::class);

        $this->service()->dryRun($fileId, $this->storeMapping($class, $columns));
    }

    public static function storeKeysThatCannotBeImported(): array
    {
        $cases = [
            'no key of the store',
            'a key of another store',
            'a key without its group',
            'a key of a field that is no store',
            'a language for a store that has none',
            'two columns into one key',
            'a store without a key',
        ];

        return array_combine($cases, array_map(static fn (string $case): array => [$case], $cases));
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

    public function testNewObjectsCannotGoBelowAnObject(): void
    {
        $object = $this->createObject(self::TARGET, 'SKU-1');
        $fileId = $this->upload("sku;name\nSKU-9;Chair\n", 'products.csv');

        $this->expectException(NotFoundHttpException::class);

        $this->service()->dryRun($fileId, $this->mappedHere(['name' => 'name'], $object->getRealFullPath()));
    }

    public function testASkippedRowsDownloadMarksFormulasAsText(): void
    {
        $fileId = $this->upload("sku;=total;name\n;=HYPERLINK(1);-12.5\n", 'products.csv');
        $service = $this->service();
        $service->dryRun($fileId, $this->mappedHere(['sku' => 'sku']));

        $skipped = $this->csvRows($service->skippedRows($fileId, AdHocImportDryRunServiceInterface::SKIP_ROWS));

        $this->assertSame(['Row', 'sku', "'=total", 'name', 'Error'], $skipped[0]);
        $this->assertSame(["'=HYPERLINK(1)", '-12.5'], array_slice($skipped[1], 2, 2));
    }

    public function testAConfigurationCannotReadAnObjectTheUserMayNotView(): void
    {
        $withHidden = $this->createObject(self::TARGET, 'SKU-1', 'Chair');
        $withVisible = $this->createObject(self::TARGET, 'SKU-2', 'Table');
        $hidden = $this->createObject(self::HIDDEN_FOLDER, 'SKU-HIDDEN', self::SECRET_NAME);
        $visible = $this->createObject(self::TARGET, 'SKU-3', 'Lamp');
        $user = $this->saveUserWithWorkspaces([
            self::TARGET => ['list' => true, 'view' => true, 'create' => true, 'save' => true],
            self::HIDDEN_FOLDER => ['list' => false, 'view' => false],
        ]);
        $this->users[] = $user;
        $this->logInAs($user);
        $fileId = $this->upload(
            sprintf(
                "ID;Reference\n%d;%d\n%d;%d\n",
                $withHidden->getId(),
                $hidden->getId(),
                $withVisible->getId(),
                $visible->getId()
            ),
            'products.csv'
        );

        $rows = $this->byRow($this->configuredDryRun($fileId, $this->class, [[
            'label' => 'Name',
            'dataSourceIndex' => ['Reference'],
            'transformationPipeline' => [
                ['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'id']],
                ['type' => 'objectField', 'settings' => ['attribute' => 'name']],
            ],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'name', 'language' => '']],
        ]], ['ID', 'Reference']));

        $this->assertCount(1, $rows[2]['invalid'], 'a hidden object counts as not found');
        $this->assertStringNotContainsString(self::SECRET_NAME, (string) json_encode($rows));
        $this->assertSame([], $rows[3]['invalid']);
        $this->assertSame([['old' => 'Table', 'new' => 'Lamp']], $rows[3]['values']);
    }

    public function testARelationToAnObjectTheUserMayNotViewIsNotShown(): void
    {
        $hidden = $this->createObject(self::HIDDEN_FOLDER, 'SKU-HIDDEN');
        $visible = $this->createObject(self::TARGET, 'SKU-2');
        $this->createObject(self::TARGET, 'SKU-1', fields: ['related' => $hidden]);
        $user = $this->saveUserWithWorkspaces([
            self::TARGET => ['list' => true, 'view' => true, 'create' => true, 'save' => true],
            self::HIDDEN_FOLDER => ['list' => false, 'view' => false],
        ]);
        $this->users[] = $user;
        $this->logInAs($user);
        $fileId = $this->upload("sku;reference\nSKU-1;" . $visible->getRealFullPath() . "\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->mappedHere(['reference' => [
            'field' => 'related',
            'transformationPipeline' => [['type' => 'loadDataObject', 'settings' => ['loadStrategy' => 'path']]],
        ]]))->getRows();

        $this->assertSame([['old' => null, 'new' => $visible->getRealFullPath()]], $rows[0]['values']);
        $this->assertStringNotContainsString('hidden', (string) json_encode($rows));
    }

    public function testAFieldAConfigurationWritesTwiceIsCheckedOnItsLastValue(): void
    {
        $class = ClassDefinition::getByName(self::MANDATORY_CLASS_NAME) ?? $this->createMandatoryClass();
        $object = $this->storeObject($class, 'SKU-1');
        $object->set('name', 'Chair');
        $object->save();
        $fileId = $this->upload("ID;Empty;Title\n" . $object->getId() . ";;Stool\n", 'products.csv');
        $name = ['type' => 'direct', 'settings' => ['fieldName' => 'name', 'language' => '']];

        $rows = $this->configuredDryRun($fileId, $class, [
            ['label' => 'Empty', 'dataSourceIndex' => ['Empty'], 'transformationPipeline' => [], 'dataTarget' => $name],
            ['label' => 'Title', 'dataSourceIndex' => ['Title'], 'transformationPipeline' => [], 'dataTarget' => $name],
        ], ['ID', 'Empty', 'Title'])->getRows();

        $this->assertSame([], $rows[0]['invalid'], 'the save sees the last value only');
        $this->assertSame('Stool', $rows[0]['values'][1]['new']);
    }

    public function testARunWhoseTargetFolderIsGoneHasNoPlan(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Data object folder 999999999 not found');

        $this->service()->planForRun(
            'file-of-the-run',
            FileType::Csv,
            new AdHocImportDryRunParameters(
                999999999,
                $this->class->getId(),
                AdHocImportDryRunParameters::MATCH_BY_ID,
                'ID'
            ),
            null,
            $this->user
        );
    }

    public function testAConfigurationsOtherTargetsKeepTheLanguageTheyWriteForTheRightsCheck(): void
    {
        $fileId = $this->upload("ID;Related;Batch;Default\n1;a;b;c\n", 'products.csv');
        $item = static fn (string $column, string $type, string $language): array => [
            'label' => $column,
            'dataSourceIndex' => [$column],
            'transformationPipeline' => [],
            'dataTarget' => ['type' => $type, 'settings' => ['fieldName' => 'name', 'language' => $language]],
        ];
        $columns = ['ID' => 'ID', 'Related' => 'Related', 'Batch' => 'Batch', 'Default' => 'Default'];

        $plan = $this->service()->planForRun(
            $fileId,
            FileType::Csv,
            new AdHocImportDryRunParameters(
                $this->folderId,
                'ignored',
                AdHocImportDryRunParameters::MATCH_BY_FIELD,
                'ignored',
                configuration: 'by-id',
                configurationColumns: $columns,
            ),
            [
                'configuration' => [
                    'classId' => $this->class->getId(),
                    'matchBy' => AdHocImportDryRunParameters::MATCH_BY_ID,
                    'matchField' => null,
                    'matchLanguage' => null,
                    'matchColumn' => 'ID',
                    'newObjectsFolder' => self::TARGET,
                ],
                'mapping' => [
                    $item('Related', 'manyToManyRelation', 'de'),
                    $item('Batch', 'classificationstoreBatch', 'fr'),
                    $item('Default', 'classificationstoreBatch', 'default'),
                ],
            ],
            $this->user
        );

        $this->assertSame(
            ['de', 'fr', null],
            array_map(static fn ($writer): ?string => $writer->field()->language, $plan->dryRun->writers)
        );
    }

    /**
     * @dataProvider namesAConfigurationMayNotWriteTo
     */
    public function testAConfigurationMappingKeyOrPathIsRefused(string $fieldName): void
    {
        $fileId = $this->upload("ID;Where\n1;/elsewhere\n", 'products.csv');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field ' . $fieldName . ' cannot be imported into.');

        $this->configuredDryRun($fileId, $this->class, [[
            'label' => 'Where',
            'dataSourceIndex' => ['Where'],
            'transformationPipeline' => [],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => $fieldName, 'language' => '']],
        ]], ['ID', 'Where']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function namesAConfigurationMayNotWriteTo(): array
    {
        return [
            'the key' => ['key'],
            'the path' => ['path'],
            'the type' => ['type'],
            // PHP finds a setter whatever the case of its name
            'the key in another case' => ['Key'],
            'the parent' => ['parentId'],
            'the published state' => ['published'],
        ];
    }

    /**
     * @dataProvider namesARelationTargetMayNotWriteTo
     */
    public function testARelationTargetOfAConfigurationCannotRenameOrMoveTheObject(string $fieldName): void
    {
        $fileId = $this->upload("ID;Where\n1;/elsewhere\n", 'products.csv');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field ' . $fieldName . ' cannot be imported into.');

        $this->configuredDryRun($fileId, $this->class, [[
            'label' => 'Where',
            'dataSourceIndex' => ['Where'],
            'transformationPipeline' => [],
            'dataTarget' => ['type' => 'manyToManyRelation', 'settings' => ['fieldName' => $fieldName]],
        ]], ['ID', 'Where']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function namesARelationTargetMayNotWriteTo(): array
    {
        return ['the key' => ['key'], 'the path' => ['path'], 'the type' => ['type']];
    }

    public function testALocalizedFieldAConfigurationGivesNoLanguageIsWrittenInTheDefaultLanguage(): void
    {
        $fileId = $this->upload("ID;Text\n1;a\n", 'products.csv');
        $brickField = 'bricks.' . RowMatcherModel::LOCALIZED_BRICK_NAME . '.code';

        $plan = $this->configurationPlan($fileId, 'Text', [
            ['direct', 'title', ''],
            ['manyToManyRelation', 'title', ''],
            ['manyToManyRelation', $brickField, null],
            ['direct', 'name', ''],
            ['direct', 'title', 'de'],
        ], $this->user);

        $default = Tool::getDefaultLanguage();
        $this->assertSame(
            [$default, $default, $default, null, 'de'],
            array_map(static fn ($writer): ?string => $writer->field()->language, $plan->dryRun->writers)
        );
    }

    public function testALanguageAConfigurationLeavesEmptyStillNeedsTheRightToEditTheDefaultLanguage(): void
    {
        $this->assertNotSame('de', Tool::getDefaultLanguage(), 'the user below may edit only another language');
        $object = $this->createObject(self::TARGET, 'SKU-1', title: 'Chair');
        $user = $this->saveUserWithWorkspaces([
            self::TARGET => [
                'list' => true,
                'view' => true,
                'create' => true,
                'save' => true,
                'publish' => true,
                'lEdit' => 'de',
            ],
        ]);
        $this->users[] = $user;
        $this->logInAs($user);
        $fileId = $this->upload(sprintf("ID;Text\n%d;Changed\n", $object->getId()), 'products.csv');
        $plan = $this->configurationPlan($fileId, 'Text', [['direct', 'title', '']], $user);

        $writer = $this->createMock(RowWriterInterface::class);
        $writer->expects($this->never())->method('write');
        $outcome = (new RowImporter(
            $writer,
            $this->tester->grabService(MappingApplicationScope::class),
            $this->messages(),
            AdHocRunFixtures::runMessages(),
            new EventDispatcher(),
            new NullLogger()
        ))->import($plan, $user, 0, []);

        $this->assertStringContainsString('may-not-edit-language', (string) $outcome->message);
        $this->assertSame('Chair', $object->get('title', 'en'), 'the mapping did not reach the object');
    }

    /**
     * @param list<array{string, string, string|null}> $items per item its target's type, field name and language
     */
    private function configurationPlan(string $fileId, string $column, array $items, User $user): ImportPlan
    {
        $mapping = array_map(static function (array $item) use ($column): array {
            [$type, $fieldName, $language] = $item;
            $settings = $language === null
                ? ['fieldName' => $fieldName]
                : ['fieldName' => $fieldName, 'language' => $language];

            return [
                'label' => $fieldName,
                'dataSourceIndex' => [$column],
                'transformationPipeline' => [],
                'dataTarget' => ['type' => $type, 'settings' => $settings],
            ];
        }, $items);

        return $this->service()->planForRun(
            $fileId,
            FileType::Csv,
            new AdHocImportDryRunParameters(
                $this->folderId,
                'ignored',
                AdHocImportDryRunParameters::MATCH_BY_FIELD,
                'ignored',
                configuration: 'by-id',
                configurationColumns: ['ID' => 'ID', $column => $column],
            ),
            [
                'configuration' => [
                    'classId' => $this->class->getId(),
                    'matchBy' => AdHocImportDryRunParameters::MATCH_BY_ID,
                    'matchField' => null,
                    'matchLanguage' => null,
                    'matchColumn' => 'ID',
                    'newObjectsFolder' => self::TARGET,
                ],
                'mapping' => $mapping,
            ],
            $user
        );
    }

    public function testALaterRowDoesNotSeeWhatAnEarlierRowChanged(): void
    {
        $class = ClassDefinition::getByName(RowMatcherModel::INHERITING_CLASS_NAME)
            ?? RowMatcherModel::createClassNamed(RowMatcherModel::INHERITING_CLASS_NAME);
        $parent = $this->storeObject($class, 'SKU-PARENT');
        $parent->set('name', 'Stored name');
        $parent->save();
        $child = $this->storeObject($class, 'SKU-CHILD');
        $child->setParent($parent);
        $child->save();
        \Pimcore::collectGarbage();
        $fileId = $this->upload("sku;name\nSKU-PARENT;Changed name\nSKU-CHILD;Own name\n", 'products.csv');

        $rows = Service::useInheritedValues(true, fn (): array => $this->byRow($this->service()->dryRun(
            $fileId,
            $this->storeMapping($class, [['column' => 'name', 'field' => 'name']])
        )));

        $this->assertSame('Changed name', $rows[2]['values'][0]['new']);
        $this->assertSame('Stored name', $rows[3]['values'][0]['old'], 'as saved, not as the row before left it');
    }

    public function testADateTimeFieldShowsAChangeOfTheTimeOfOneDay(): void
    {
        $class = ClassDefinition::getByName(self::DATE_TIME_CLASS_NAME) ?? $this->createDateTimeClass();
        $object = $this->storeObject($class, 'SKU-1');
        $object->set('starts', Carbon::parse('2026-11-07 10:00'));
        $object->save();
        $fileId = $this->upload("ID;Starts\n" . $object->getId() . ";2026-11-07 00:00\n", 'products.csv');

        $rows = $this->configuredDryRun($fileId, $class, [[
            'label' => 'Starts',
            'dataSourceIndex' => ['Starts'],
            'transformationPipeline' => [['type' => 'date', 'settings' => ['format' => 'Y-m-d H:i']]],
            'dataTarget' => ['type' => 'direct', 'settings' => ['fieldName' => 'starts', 'language' => '']],
        ]], ['ID', 'Starts'])->getRows();

        $this->assertSame(
            [['old' => '2026-11-07 10:00:00', 'new' => '2026-11-07 00:00:00']],
            $rows[0]['values']
        );
    }

    public function testAStoreKeyRefusedInSeveralLanguagesMarksEachOfItsColumns(): void
    {
        [$class, $weight] = $this->storeClass('MandatoryLocalized', localized: true, mandatoryInGroup: true);
        $object = $this->storeObject($class, 'SKU-1');
        $object->save();
        $fileId = $this->upload("sku;english;german\nSKU-1;;\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'english', 'field' => 'attributes', 'key' => $weight->id(), 'language' => 'en'],
            ['column' => 'german', 'field' => 'attributes', 'key' => $weight->id(), 'language' => 'de'],
        ]))->getRows();

        $this->assertSame([0, 1], array_column($rows[0]['invalid'], 'field'));
        $this->assertCount(1, array_unique(array_column($rows[0]['invalid'], 'message')));
    }

    public function testAStoreKeyOfAConfigurationIsCheckedLikeAColumn(): void
    {
        [$class, $weight] = $this->storeClass('MandatoryInGroup', mandatoryInGroup: true);
        $object = $this->storeObject($class, 'SKU-1');
        $object->save();
        $fileId = $this->upload("ID;Weight\n" . $object->getId() . ";5 kg\n", 'products.csv');
        $mapping = [[
            'label' => 'Weight',
            'dataSourceIndex' => ['Weight'],
            'transformationPipeline' => [],
            'dataTarget' => [
                'type' => 'classificationstore',
                'settings' => ['fieldName' => 'attributes', 'keyId' => $weight->id(), 'language' => 'default'],
            ],
        ]];

        $response = $this->configuredDryRun($fileId, $class, $mapping, ['ID', 'Weight']);

        $this->assertSame(
            [['key' => 'attributes', 'language' => null, 'label' => 'Basics › weight']],
            $response->getFields()
        );
        $row = $response->getRows()[0];
        $this->assertSame([['old' => null, 'new' => '5 kg']], $row['values']);
        $this->assertSame(['Basics › height is required'], array_column($row['invalid'], 'message'));
        $this->assertTrue($row['blocked'], 'the group the key activates is checked');

        $mapping[0]['dataTarget']['settings']['keyId'] = '999999-999999';
        $this->expectException(InvalidArgumentException::class);
        $this->configuredDryRun($fileId, $class, $mapping, ['ID', 'Weight']);
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

        $this->assertSame(self::FOUND_OUTSIDE_TARGET, $message);
    }

    public function testAMatchedObjectTheUserMayNotViewIsAnErrorRowThatTellsNothingOfIt(): void
    {
        $this->createObject(self::TARGET, 'SKU-1', 'Chair');
        $this->createObject(self::HIDDEN_FOLDER, 'SKU-HIDDEN', self::SECRET_NAME, '9.75');
        $this->createObject(self::ELSEWHERE, 'SKU-OUT', 'Elsewhere name');
        $this->createObject(self::CLOSED_FOLDER, 'SKU-CLOSED', self::CLOSED_NAME);
        $user = $this->saveUserWithWorkspaces([
            self::TARGET => ['list' => true, 'view' => true, 'create' => true, 'save' => true],
            self::HIDDEN_FOLDER => ['list' => false, 'view' => false, 'create' => true, 'save' => true],
            self::ELSEWHERE => ['list' => true, 'view' => true],
        ]);
        $this->users[] = $user;
        $this->logInAs($user);
        $fileId = $this->upload(
            "sku;name\nSKU-HIDDEN;Armchair\nSKU-1;Stool\nSKU-OUT;Desk\nSKU-CLOSED;Bench\n",
            'products.csv'
        );

        $service = $this->service();
        $response = $service->dryRun($fileId, $this->mappedHere(['sku' => 'sku', 'name' => 'name']));

        $rows = $this->byRow($response);
        $this->assertNull($rows[2]['action']);
        $this->assertSame('You may not view the object with sku SKU-HIDDEN', $rows[2]['error']);
        $this->assertSame([['old' => null, 'new' => 'Armchair']], $rows[2]['values'], 'only the file\'s value');
        $this->assertStringNotContainsString(self::SECRET_NAME, (string) json_encode($response->getRows()));
        $this->assertStringNotContainsString('hidden', (string) json_encode($rows[2]));

        $this->assertSame('update', $rows[3]['action'], 'an object the user may view is updated');
        $this->assertSame([['old' => 'Chair', 'new' => 'Stool']], $rows[3]['values']);
        $this->assertSame('Found in ' . self::ELSEWHERE . ', outside ' . self::TARGET, $rows[4]['error']);
        $this->assertSame(self::FOUND_OUTSIDE_TARGET, $rows[5]['error']);

        $skippedInEitherMode = array_map(
            fn (string $errorMode): array => array_column(
                array_slice($this->csvRows($service->skippedRows($fileId, $errorMode)), 1),
                0
            ),
            [
                AdHocImportDryRunServiceInterface::SKIP_ROWS,
                AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES,
            ]
        );
        $this->assertSame([['2', '4', '5'], ['2', '4', '5']], $skippedInEitherMode);
    }

    // each row tells why it fails, never what a hidden object holds or where it lies
    public function testAUserWithoutViewRightIsToldOnlyWhyTheRowsOfHiddenObjectsFail(): void
    {
        $visible = $this->createObject(self::TARGET, 'SKU-1', 'Chair');
        $hidden = $this->createObject(self::HIDDEN_FOLDER, 'SKU-SHARED', self::SECRET_NAME, '9.75');
        $this->createObject(self::TARGET, 'SKU-SHARED', 'Shared name');
        $closed = $this->createObject(self::CLOSED_FOLDER, 'SKU-CLOSED', self::CLOSED_NAME);
        $otherClass = ClassDefinition::getByName(self::MANDATORY_CLASS_NAME) ?? $this->createMandatoryClass();
        $fqcn = self::OBJECT_NAMESPACE . $otherClass->getName();
        /** @var Concrete $ofOtherClass */
        $ofOtherClass = new $fqcn();
        $ofOtherClass->setParent(Service::createFolderByPath(self::HIDDEN_FOLDER));
        $ofOtherClass->setKey('other-class');
        $ofOtherClass->set('name', 'Other name');
        $ofOtherClass->save();
        $user = $this->saveUserWithWorkspaces([
            self::TARGET => ['list' => true, 'view' => true, 'create' => true, 'save' => true],
            self::HIDDEN_FOLDER => ['list' => false, 'view' => false, 'create' => true, 'save' => true],
        ]);
        $this->users[] = $user;
        $this->logInAs($user);

        $byId = $this->upload($this->xlsx([
            ['ID', 'Title'],
            [(string) $visible->getId(), 'Stool'],
            [(string) $hidden->getId(), 'Armchair'],
            [(string) $closed->getId(), 'Bench'],
            [(string) $ofOtherClass->getId(), 'Crate'],
            ['999999999', 'Ghost'],
        ]), 'products.xlsx');
        $response = $this->service()->dryRun($byId, new AdHocImportDryRunParameters(
            $this->folderId,
            $this->class->getId(),
            AdHocImportDryRunParameters::MATCH_BY_ID,
            '0',
            columns: [['column' => '1', 'field' => 'name']]
        ));

        $rows = $this->byRow($response);
        $this->assertSame('update', $rows[2]['action'], 'an object the user may view is updated');
        $this->assertSame(
            [
                3 => 'You may not view the object with Object ID ' . $hidden->getId(),
                4 => self::FOUND_OUTSIDE_TARGET,
                5 => 'The ID ' . $ofOtherClass->getId() . ' belongs to a folder or an object of another class',
                6 => 'No object has the ID 999999999',
            ],
            array_map(static fn (array $row): ?string => $row['error'], array_slice($rows, 1, null, true))
        );
        $this->assertSame([['old' => null, 'new' => 'Armchair']], $rows[3]['values'], 'only the file\'s value');
        $answer = (string) json_encode($response->getRows());
        foreach ([self::SECRET_NAME, self::CLOSED_NAME, 'Other name', 'closed', 'hidden'] as $told) {
            $this->assertStringNotContainsString($told, $answer);
        }

        $bySku = $this->upload("sku;name\nSKU-SHARED;Desk\n", 'products.csv');
        $response = $this->service()->dryRun($bySku, $this->mappedHere(['sku' => 'sku', 'name' => 'name']));

        $row = $response->getRows()[0];
        $this->assertNull($row['action']);
        $this->assertSame('2 objects have sku SKU-SHARED', $row['error'], 'the hidden match is counted');
        $this->assertSame([['old' => null, 'new' => 'Desk']], $row['values']);
        $this->assertStringNotContainsString(self::SECRET_NAME, (string) json_encode($response->getRows()));
        $this->assertStringNotContainsString('Shared name', (string) json_encode($response->getRows()));
    }

    // whether a multiselect's options are known only at runtime, or it has none, any value fits
    public function testAMultiselectWithDynamicOrNoOptionsTakesAnyValue(): void
    {
        $class = ClassDefinition::getByName(self::OPTIONS_CLASS_NAME) ?? $this->createOptionsClass();
        $fileId = $this->upload("sku;open;dynamic;fixed\nSKU-1;any,thing;XL;XL\n", 'products.csv');
        $explode = [['type' => 'explode', 'settings' => ['delimiter' => ',']]];

        $rows = $this->service()->dryRun($fileId, new AdHocImportDryRunParameters(
            $this->folderId,
            $class->getId(),
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            'sku',
            'sku',
            columns: [
                ['column' => 'open', 'field' => 'open', 'transformationPipeline' => $explode],
                ['column' => 'dynamic', 'field' => 'dynamic', 'transformationPipeline' => $explode],
                ['column' => 'fixed', 'field' => 'fixed', 'transformationPipeline' => $explode],
            ]
        ))->getRows();

        $this->assertSame('create', $rows[0]['action']);
        $this->assertSame(
            [['field' => 2, 'label' => 'fixed', 'value' => 'XL', 'message' => 'fixed “XL” is no option of fixed']],
            $rows[0]['invalid'],
            'only the multiselect with static options refuses a value it does not offer'
        );
    }

    // a group deleted since an object activated it has no keys left to check
    public function testAStoreGroupDeletedSinceTheObjectActivatedItIsNotChecked(): void
    {
        [$class, $weight] = $this->storeClass('DeletedGroup');
        $width = $this->keyOfAnotherGroup('DeletedGroup');
        $object = $this->storeObject($class, 'SKU-1');
        $object->get('attributes')->setLocalizedKeyValue($weight->groupId, $weight->keyId, '1 kg');
        $object->get('attributes')->setActiveGroups([$weight->groupId => true, $width->groupId => true]);
        $object->save();
        $deleted = Classificationstore\GroupConfig::getById($width->groupId);
        $this->assertNotNull($deleted);
        $deleted->delete();
        $fileId = $this->upload("sku;weight\nSKU-1;2 kg\n", 'products.csv');

        $rows = $this->service()->dryRun($fileId, $this->storeMapping($class, [
            ['column' => 'weight', 'field' => 'attributes', 'key' => $weight->id()],
        ]))->getRows();

        $this->assertSame('update', $rows[0]['action']);
        $this->assertSame([['old' => '1 kg', 'new' => '2 kg']], $rows[0]['values']);
        $this->assertSame([], $rows[0]['invalid']);
        $this->assertFalse($rows[0]['blocked']);
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
        ?string $title = null,
        array $fields = []
    ): Concrete {
        $fqcn = self::OBJECT_NAMESPACE . self::CLASS_NAME;
        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent(Service::createFolderByPath($folder));
        $object->setKey(uniqid('o', true));
        $object->setPublished(true);
        $object->set('sku', $sku);
        $object->set('name', $name);
        $object->set('price', $price);
        $object->set('title', $title, 'en');
        foreach ($fields as $field => $value) {
            $object->set($field, $value);
        }
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

    private function createDateTimeClass(): ClassDefinition
    {
        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');
        $sku = new ClassDefinition\Data\Input();
        $sku->setName('sku');
        $sku->setTitle('sku');
        $panel->addChild($sku);
        $starts = new ClassDefinition\Data\Datetime();
        $starts->setName('starts');
        $starts->setTitle('starts');
        $panel->addChild($starts);

        $class = new ClassDefinition();
        $class->setName(self::DATE_TIME_CLASS_NAME);
        $class->setLayoutDefinitions($panel);
        $class->save();

        return $class;
    }

    private function createOptionsClass(): ClassDefinition
    {
        $panel = new ClassDefinition\Layout\Panel();
        $panel->setName('pimcore_root');
        $sku = new ClassDefinition\Data\Input();
        $sku->setName('sku');
        $sku->setTitle('sku');
        $panel->addChild($sku);
        foreach (['open' => [false, []], 'dynamic' => [true, ['S']], 'fixed' => [false, ['S']]] as $name => $kind) {
            [$dynamic, $options] = $kind;
            $multiselect = new ClassDefinition\Data\Multiselect();
            $multiselect->setName($name);
            $multiselect->setTitle($name);
            $multiselect->setOptions(array_map(
                static fn (string $option): array => ['key' => $option, 'value' => $option],
                $options
            ));
            if ($dynamic) {
                // the stored definition never keeps the runtime dynamicOptions flag;
                // a provider is what makes options dynamic
                $multiselect->setOptionsProviderType(ClassDefinition\Data\OptionsProviderInterface::TYPE_CLASS);
                $multiselect->setOptionsProviderClass(CountryOptionsProvider::class);
            }
            $panel->addChild($multiselect);
        }

        $class = new ClassDefinition();
        $class->setName(self::OPTIONS_CLASS_NAME);
        $class->setLayoutDefinitions($panel);
        $class->save();

        return $class;
    }

    /**
     * A class with a classification store of two keys in one group; the store's configuration stays for the next test.
     * Each variant has a store and a class of its own.
     *
     * @return array{ClassDefinition, StoreKey, StoreKey}
     */
    private function storeClass(
        string $variant = '',
        bool $localized = false,
        bool $mandatory = false,
        bool $mandatoryInGroup = false,
        bool $allowInherit = false,
        ?int $maxItems = null
    ): array {
        $storeName = self::STORE_NAME . strtolower($variant);
        $store = Classificationstore\StoreConfig::getByName($storeName);
        if ($store === null) {
            $store = new Classificationstore\StoreConfig();
            $store->setName($storeName);
            $store->save();
        }
        $group = Classificationstore\GroupConfig::getByName('Basics', $store->getId());
        if ($group === null) {
            $group = new Classificationstore\GroupConfig();
            $group->setStoreId($store->getId());
            $group->setName('Basics');
            $group->save();
        }
        $keys = [];
        foreach (['weight', 'height'] as $name) {
            $key = Classificationstore\KeyConfig::getByName($name, $store->getId());
            if ($key === null) {
                $key = new Classificationstore\KeyConfig();
                $definition = new ClassDefinition\Data\Input();
                $definition->setName($name);
                $definition->setMandatory($mandatory);
                $key->setDefinition((string) json_encode($definition));
                $key->setStoreId($store->getId());
                $key->setName($name);
                $key->setEnabled(true);
                $key->setType('input');
                $key->save();
            }
            if (Classificationstore\KeyGroupRelation::getByGroupAndKeyId($group->getId(), $key->getId()) === null) {
                $relation = new Classificationstore\KeyGroupRelation();
                $relation->setGroupId($group->getId());
                $relation->setKeyId($key->getId());
                $relation->setMandatory($mandatoryInGroup);
                $relation->save();
            }
            $keys[] = new StoreKey($group->getId(), $key->getId());
        }

        $className = self::STORE_CLASS_NAME . $variant;
        $class = ClassDefinition::getByName($className);
        if ($class === null) {
            $panel = new ClassDefinition\Layout\Panel();
            $panel->setName('pimcore_root');
            $sku = new ClassDefinition\Data\Input();
            $sku->setName('sku');
            $sku->setTitle('sku');
            $panel->addChild($sku);
            $attributes = new ClassDefinition\Data\Classificationstore();
            $attributes->setName('attributes');
            $attributes->setTitle('Attributes');
            $attributes->setStoreId($store->getId());
            $attributes->setLocalized($localized);
            $attributes->setMaxItems($maxItems);
            $panel->addChild($attributes);

            $class = new ClassDefinition();
            $class->setName($className);
            $class->setAllowInherit($allowInherit);
            $class->setLayoutDefinitions($panel);
            $class->save();
        }

        return [$class, ...$keys];
    }

    /**
     * A key of type quantityValue in the variant's store, which no transformation of a column leads to.
     */
    private function quantityKey(string $variant, bool $mandatoryInGroup = false): StoreKey
    {
        $store = Classificationstore\StoreConfig::getByName(self::STORE_NAME . strtolower($variant));
        $this->assertNotNull($store);
        $group = Classificationstore\GroupConfig::getByName('Basics', $store->getId());
        $this->assertNotNull($group);
        $key = Classificationstore\KeyConfig::getByName('length', $store->getId());
        if ($key === null) {
            $definition = new ClassDefinition\Data\QuantityValue();
            $definition->setName('length');
            $key = new Classificationstore\KeyConfig();
            $key->setDefinition((string) json_encode($definition));
            $key->setStoreId($store->getId());
            $key->setName('length');
            $key->setEnabled(true);
            $key->setType('quantityValue');
            $key->save();
        }
        if (Classificationstore\KeyGroupRelation::getByGroupAndKeyId($group->getId(), $key->getId()) === null) {
            $relation = new Classificationstore\KeyGroupRelation();
            $relation->setGroupId($group->getId());
            $relation->setKeyId($key->getId());
            $relation->setMandatory($mandatoryInGroup);
            $relation->save();
        }

        return new StoreKey($group->getId(), $key->getId());
    }

    /**
     * A text key in a second group of the variant's store.
     */
    private function keyOfAnotherGroup(string $variant, ?string $regex = null): StoreKey
    {
        $store = Classificationstore\StoreConfig::getByName(self::STORE_NAME . strtolower($variant));
        $this->assertNotNull($store);
        $group = new Classificationstore\GroupConfig();
        $group->setStoreId($store->getId());
        $group->setName('Dimensions');
        $group->save();

        $definition = new ClassDefinition\Data\Input();
        $definition->setName('width');
        if ($regex !== null) {
            $definition->setRegex($regex);
        }
        $key = new Classificationstore\KeyConfig();
        $key->setDefinition((string) json_encode($definition));
        $key->setStoreId($store->getId());
        $key->setName('width');
        $key->setEnabled(true);
        $key->setType('input');
        $key->save();

        $relation = new Classificationstore\KeyGroupRelation();
        $relation->setGroupId($group->getId());
        $relation->setKeyId($key->getId());
        $relation->save();

        return new StoreKey($group->getId(), $key->getId());
    }

    private function storeObject(ClassDefinition $class, string $sku): Concrete
    {
        $fqcn = self::OBJECT_NAMESPACE . $class->getName();
        /** @var Concrete $object */
        $object = new $fqcn();
        $object->setParent(DataObject::getById($this->folderId));
        $object->setKey(uniqid('o', true));
        $object->setPublished(true);
        $object->set('sku', $sku);

        return $object;
    }

    /**
     * @param list<array<string, string>> $columns
     */
    private function storeMapping(ClassDefinition $class, array $columns): AdHocImportDryRunParameters
    {
        return new AdHocImportDryRunParameters(
            $this->folderId,
            $class->getId(),
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            'sku',
            'sku',
            columns: $columns
        );
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

    /**
     * A dry run of the file with a configuration of the class that matches by the file's ID column.
     *
     * @param list<array<string, mixed>> $mapping
     * @param list<string> $columns the file's columns
     */
    private function configuredDryRun(
        string $fileId,
        ClassDefinition $class,
        array $mapping,
        array $columns
    ): AdHocImportDryRunResponse {
        $configuration = $this->createStub(Configuration::class);
        $configuration->method('getName')->willReturn('by-id');
        $configuration->method('getType')->willReturn('dataImporterDataObject');
        $configuration->method('isAllowed')->willReturn(true);
        $configuration->method('getConfiguration')->willReturn([
            'interpreterConfig' => ['type' => 'csv', 'settings' => []],
            'resolverConfig' => [
                'dataObjectClassId' => $class->getId(),
                'loadingStrategy' => ['type' => 'id', 'settings' => ['dataSourceIndex' => 'ID']],
                'createLocationStrategy' => ['type' => 'staticPath', 'settings' => ['path' => self::TARGET]],
            ],
            'mappingConfig' => $mapping,
        ]);

        return $this->service($this->configurationService([$configuration]))->dryRun(
            $fileId,
            new AdHocImportDryRunParameters(
                $this->folderId,
                'ignored',
                AdHocImportDryRunParameters::MATCH_BY_FIELD,
                'ignored',
                configuration: 'by-id',
                configurationColumns: array_combine($columns, $columns),
            )
        );
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
        $this->logInAs($user);

        return $user;
    }

    private function logInAs(User $user): void
    {
        $securityUser = new SecurityUser($user);
        $this->tokenStorage()->setToken(
            new UsernamePasswordToken($securityUser, 'pimcore_studio', $securityUser->getRoles())
        );
        $this->user = $user;
    }

    /**
     * @param array<string, array<string, bool|string>> $rights per folder path its rights
     */
    private function saveUserWithWorkspaces(array $rights): User
    {
        $workspaces = [];
        foreach ($rights as $path => $folderRights) {
            $workspace = new ObjectWorkspace();
            $workspace->setCid((int) Service::createFolderByPath($path)->getId());
            $workspace->setCpath($path);
            $workspace->setValues($folderRights);
            $workspaces[] = $workspace;
        }

        $user = new User();
        $user->setParentId(0);
        $user->setName(uniqid('ad-hoc-dry-run-', true));
        $user->setActive(true);
        $user->setAdmin(false);
        $user->setPermission('objects', true);
        $user->setWorkspacesObject($workspaces);
        $user->save();

        return $user;
    }

    private function tokenStorage(): TokenStorageInterface
    {
        return $this->tester->grabService('security.token_storage');
    }
}
