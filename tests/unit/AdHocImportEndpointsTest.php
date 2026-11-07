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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\DeleteFileController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\InspectFileController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\LoadClassificationStoreKeysController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\LoadMappingController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\LoadMatchFieldsController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\UploadFileController;
use Pimcore\Bundle\DataImporterBundle\Installer;
use Pimcore\Bundle\DataImporterBundle\Migrations\Version20261106170000;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileSettings;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportMappingResponse;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Util\Constant\CacheKeys;
use Pimcore\Cache;
use Pimcore\Cache\Core\CoreCacheHandler;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

class AdHocImportEndpointsTest extends Unit
{
    private const ROUTE_PREFIX = 'pimcore_studio_api_data_importer_ad_hoc_import_';

    private const ENDPOINTS = [
        UploadFileController::class => ['uploadFile', 'upload_file', 'POST', '/ad-hoc-import/file'],
        InspectFileController::class => ['inspectFile', 'inspect_file', 'POST', '/ad-hoc-import/file/{fileId}/inspect'],
        DeleteFileController::class => ['deleteFile', 'delete_file', 'DELETE', '/ad-hoc-import/file/{fileId}'],
        LoadMatchFieldsController::class => [
            'loadMatchFields',
            'load_match_fields',
            'GET',
            '/ad-hoc-import/match-fields',
        ],
        LoadMappingController::class => ['loadMapping', 'load_mapping', 'POST', '/ad-hoc-import/mapping'],
        LoadClassificationStoreKeysController::class => [
            'loadClassificationStoreKeys',
            'load_classification_store_keys',
            'GET',
            '/ad-hoc-import/classification-store-keys',
        ],
    ];

    protected UnitTester $tester;

    public function testEveryEndpointNeedsTheRightOfItsOwnAndNoDataHubRight(): void
    {
        foreach (self::ENDPOINTS as $controller => [$method]) {
            $attributes = (new \ReflectionMethod($controller, $method))->getAttributes(IsGranted::class);

            $this->assertCount(1, $attributes, $controller);
            $this->assertSame(
                [PermissionConstants::PLUGIN_DATA_IMPORTER_AD_HOC_IMPORT],
                $attributes[0]->getArguments(),
                $controller
            );
        }
    }

    public function testEveryEndpointIsWired(): void
    {
        foreach (array_keys(self::ENDPOINTS) as $controller) {
            $this->assertInstanceOf($controller, $this->tester->grabService($controller));
        }
    }

    /**
     * Read from the attributes and the bundle's routing file: the test kernel loads no routes.
     */
    public function testEveryEndpointIsRoutedBelowTheBundlesPrefix(): void
    {
        $routes = Yaml::parseFile(__DIR__ . '/../../src/Resources/config/pimcore/routing.yml');
        $routing = $routes['pimcore_data_importer_studio'];
        $this->assertSame('@PimcoreDataImporterBundle/Controller/Studio', $routing['resource']);
        $this->assertSame('attribute', $routing['type']);
        $this->assertStringEndsWith('/bundle/data-importer', $routing['prefix']);

        foreach (self::ENDPOINTS as $controller => [$method, $routeName, $httpMethod, $path]) {
            $attributes = (new \ReflectionMethod($controller, $method))->getAttributes(Route::class);
            $this->assertCount(1, $attributes, $controller);

            $route = $attributes[0]->newInstance();
            $this->assertSame(self::ROUTE_PREFIX . $routeName, $route->getName());
            $this->assertSame([$httpMethod], $route->getMethods(), $controller);
            $this->assertSame($path, $route->getPath(), $controller);
        }
    }

    public function testSettingsArriveFromTheRequestAndDefaultToTheFile(): void
    {
        /** @var DenormalizerInterface $serializer */
        $serializer = $this->tester->grabService('serializer');

        $given = $serializer->denormalize(
            ['delimiter' => ';', 'enclosure' => "'", 'escape' => '', 'hasHeaderRow' => false, 'sheetName' => 'Notes'],
            AdHocImportFileSettings::class
        )->toInput();
        $this->assertSame(';', $given->delimiter);
        $this->assertSame("'", $given->enclosure);
        $this->assertSame('', $given->escape);
        $this->assertFalse($given->hasHeaderRow);
        $this->assertSame('Notes', $given->sheetName);

        $default = $serializer->denormalize([], AdHocImportFileSettings::class)->toInput();
        $this->assertNull($default->delimiter);
        $this->assertTrue($default->hasHeaderRow);
    }

    public function testAnAnswerReachesTheStudioWithItsFieldNames(): void
    {
        /** @var SerializerInterface $serializer */
        $serializer = $this->tester->grabService('serializer');

        $response = new AdHocImportFileResponse(
            5000,
            'file-1',
            'csv',
            delimiter: ';',
            detectedDelimiter: ';',
            rowCount: 2,
            columns: [['dataIndex' => 'sku', 'label' => 'SKU']],
            firstRows: [['A-1'], [null]]
        );
        $answer = json_decode($serializer->serialize($response, 'json'), true);

        $this->assertSame(5000, $answer['maxRows']);
        $this->assertSame('file-1', $answer['fileId']);
        $this->assertSame(';', $answer['detectedDelimiter']);
        $this->assertTrue($answer['hasHeaderRow']);
        $this->assertSame([['dataIndex' => 'sku', 'label' => 'SKU']], $answer['columns']);
        $this->assertSame([['A-1'], [null]], $answer['firstRows']);
    }

    public function testAMappingReachesTheStudioWithItsFieldNames(): void
    {
        /** @var SerializerInterface $serializer */
        $serializer = $this->tester->grabService('serializer');

        $field = [
            'key' => 'name',
            'title' => 'Name',
            'localized' => true,
            'mandatory' => true,
            'transformationResultType' => 'default',
        ];
        $suggestion = [
            'sourceIndex' => 'Name DE',
            'sourceLabel' => 'Name DE',
            'targetFieldName' => 'name',
            'targetFieldLabel' => 'Name',
            'transformationResultType' => 'default',
            'score' => 100,
            'language' => 'de',
        ];
        $answer = json_decode(
            $serializer->serialize(new AdHocImportMappingResponse([$field], [$suggestion]), 'json'),
            true
        );

        $this->assertSame(['fields' => [$field], 'suggestions' => [$suggestion]], $answer);
    }

    public function testTheMigrationAddsTheRightUnlessItExistsAndRemovesItAgain(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));
        $connection->method('createSchemaManager')->willReturn($this->createMock(AbstractSchemaManager::class));

        $up = new Version20261106170000($connection, new NullLogger());
        $this->assertStringContainsString('right', $up->getDescription());
        $this->assertSame('PimcoreDataImporterBundle', (new \ReflectionMethod($up, 'getBundleName'))->invoke($up));
        $up->up(new Schema());
        [$insert] = $up->getSql();
        $this->assertStringContainsString('INSERT IGNORE INTO users_permission_definitions', $insert->getStatement());
        $this->assertSame(Installer::AD_HOC_IMPORT_PERMISSION, $insert->getParameters()[0]);

        $down = new Version20261106170000($connection, new NullLogger());
        $down->down(new Schema());
        [$delete] = $down->getSql();
        $this->assertStringContainsString('DELETE FROM users_permission_definitions', $delete->getStatement());
        $this->assertSame([Installer::AD_HOC_IMPORT_PERMISSION], $delete->getParameters());
    }

    public function testTheMigrationDropsTheCachedListOfRightsBothWays(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));
        $connection->method('createSchemaManager')->willReturn($this->createMock(AbstractSchemaManager::class));
        $migration = new Version20261106170000($connection, new NullLogger());

        $handler = $this->createMock(CoreCacheHandler::class);
        $handler->expects($this->exactly(2))->method('remove')->with(CacheKeys::USER_PERMISSIONS->value);

        $cachedHandler = new \ReflectionProperty(Cache::class, 'handler');
        $before = $cachedHandler->getValue();
        $cachedHandler->setValue(null, $handler);

        try {
            $migration->postUp(new Schema());
            $migration->postDown(new Schema());
        } finally {
            $cachedHandler->setValue(null, $before);
        }
    }
}
