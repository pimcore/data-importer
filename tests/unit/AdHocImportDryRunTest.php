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
use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ValidationException;
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

    private const string HEIGHT_VALUE = '30 cm';

    private const string FILE_WITHOUT_WEIGHT = "sku;weight\nSKU-1;\n";

    private const string OBJECT_NAMESPACE = '\\Pimcore\\Model\\DataObject\\';

    private const string MANDATORY_CLASS_NAME = 'DiDryRunMandatoryClass';

    private const string STORE_CLASS_NAME = 'DiDryRunStoreClass';

    private const string STORE_NAME = 'di-dry-run-store';

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
        $this->assertSame([['old' => '1 kg', 'new' => '2 kg'], ['old' => null, 'new' => self::HEIGHT_VALUE]], $changed['values']);
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
        bool $allowInherit = false
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
    private function quantityKey(string $variant): StoreKey
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
            $relation->save();
        }

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
