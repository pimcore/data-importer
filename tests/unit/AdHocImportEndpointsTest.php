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
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\LoadMatchFieldsController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\UploadFileController;
use Pimcore\Bundle\DataImporterBundle\Installer;
use Pimcore\Bundle\DataImporterBundle\Migrations\Version20261106170000;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileSettings;
use Pimcore\Bundle\DataImporterBundle\Tests\UnitTester;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class AdHocImportEndpointsTest extends Unit
{
    private const ENDPOINTS = [
        UploadFileController::class => ['uploadFile', 'pimcore_studio_api_data_importer_ad_hoc_import_upload_file', 'POST'],
        InspectFileController::class => ['inspectFile', 'pimcore_studio_api_data_importer_ad_hoc_import_inspect_file', 'POST'],
        DeleteFileController::class => ['deleteFile', 'pimcore_studio_api_data_importer_ad_hoc_import_delete_file', 'DELETE'],
        LoadMatchFieldsController::class => ['loadMatchFields', 'pimcore_studio_api_data_importer_ad_hoc_import_load_match_fields', 'GET'],
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

    public function testEveryEndpointIsWiredAndRouted(): void
    {
        /** @var RouterInterface $router */
        $router = $this->tester->grabService('router');

        foreach (self::ENDPOINTS as $controller => [, $routeName, $httpMethod]) {
            $this->assertInstanceOf($controller, $this->tester->grabService($controller));

            $route = $router->getRouteCollection()->get($routeName);
            $this->assertNotNull($route, $routeName);
            $this->assertSame([$httpMethod], $route->getMethods(), $routeName);
            $this->assertStringContainsString('/bundle/data-importer/ad-hoc-import/', $route->getPath());
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

        $answer = json_decode($serializer->serialize(
            new AdHocImportFileResponse(
                5000,
                'file-1',
                'csv',
                delimiter: ';',
                detectedDelimiter: ';',
                rowCount: 2,
                columns: [['dataIndex' => 'sku', 'label' => 'SKU']],
                firstRows: [['A-1'], [null]]
            ),
            'json'
        ), true);

        $this->assertSame(5000, $answer['maxRows']);
        $this->assertSame('file-1', $answer['fileId']);
        $this->assertSame(';', $answer['detectedDelimiter']);
        $this->assertTrue($answer['hasHeaderRow']);
        $this->assertSame([['dataIndex' => 'sku', 'label' => 'SKU']], $answer['columns']);
        $this->assertSame([['A-1'], [null]], $answer['firstRows']);
    }

    public function testTheMigrationAddsTheRightUnlessItExistsAndRemovesItAgain(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));
        $connection->method('createSchemaManager')->willReturn($this->createMock(AbstractSchemaManager::class));

        $up = new Version20261106170000($connection, new NullLogger());
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
}
