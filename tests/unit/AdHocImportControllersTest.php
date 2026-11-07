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
use Pimcore\Bundle\DataImporterBundle\AdHocImport\SettingsInput;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\DeleteFileController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\InspectFileController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\LoadMappingController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\LoadMatchFieldsController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\UploadFileController;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportFileSettings;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportMappingResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ClassAttributesResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\MappingSuggestionParameters;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportMappingServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportServiceInterface;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

class AdHocImportControllersTest extends Unit
{
    private const ANSWER = '{"answer":true}';

    public function testAnUploadWithoutAFileIsAnError(): void
    {
        $service = $this->createMock(AdHocImportServiceInterface::class);
        $service->expects($this->never())->method('upload');

        $this->expectException(EnvironmentException::class);

        (new UploadFileController($this->serializer(), $service))->uploadFile(new Request([], ['folderId' => '77']));
    }

    public function testAnUploadPassesItsFileAndFolderOn(): void
    {
        $file = new UploadedFile(__FILE__, 'prices.csv', null, null, true);
        $answer = new AdHocImportFileResponse(5000);

        $service = $this->createMock(AdHocImportServiceInterface::class);
        $service->expects($this->once())->method('upload')->with($file, 77)->willReturn($answer);

        $response = (new UploadFileController($this->serializer($answer), $service))->uploadFile(
            new Request([], ['folderId' => '77'], [], [], ['file' => $file])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::ANSWER, $response->getContent());
    }

    public function testInspectingPassesTheFileAndItsSettingsOn(): void
    {
        $answer = new AdHocImportFileResponse(5000);

        $service = $this->createMock(AdHocImportServiceInterface::class);
        $service->expects($this->once())->method('inspect')->with(
            'file-1',
            $this->callback(
                static fn (SettingsInput $input): bool => $input->delimiter === ';' && !$input->hasHeaderRow
            )
        )->willReturn($answer);

        $response = (new InspectFileController($this->serializer($answer), $service))->inspectFile(
            'file-1',
            new AdHocImportFileSettings(delimiter: ';', hasHeaderRow: false)
        );

        $this->assertSame(self::ANSWER, $response->getContent());
    }

    public function testDeletingPassesTheFileOnAndAnswersWithoutContent(): void
    {
        $service = $this->createMock(AdHocImportServiceInterface::class);
        $service->expects($this->once())->method('delete')->with('file-1');

        $response = (new DeleteFileController($this->serializer(), $service))->deleteFile('file-1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testTheFieldsToMatchByAreAskedForTheClass(): void
    {
        $answer = new ClassAttributesResponse([]);

        $service = $this->createMock(AdHocImportServiceInterface::class);
        $service->expects($this->once())->method('loadMatchFields')->with('PROD')->willReturn($answer);

        $response = (new LoadMatchFieldsController($this->serializer($answer), $service))->loadMatchFields('PROD');

        $this->assertSame(self::ANSWER, $response->getContent());
    }

    public function testTheMappingIsAskedForTheClassAndTheColumns(): void
    {
        $parameters = new MappingSuggestionParameters('PROD', [['index' => 'sku', 'label' => 'SKU']]);
        $answer = new AdHocImportMappingResponse([], []);

        $service = $this->createMock(AdHocImportMappingServiceInterface::class);
        $service->expects($this->once())->method('loadMapping')->with($parameters)->willReturn($answer);

        $response = (new LoadMappingController($this->serializer($answer), $service))->loadMapping($parameters);

        $this->assertSame(self::ANSWER, $response->getContent());
    }

    public function testTheSettingsShowWhatWasGiven(): void
    {
        $settings = new AdHocImportFileSettings(';', "'", '', false, 'Notes');

        $this->assertSame(';', $settings->getDelimiter());
        $this->assertSame("'", $settings->getEnclosure());
        $this->assertSame('', $settings->getEscape());
        $this->assertFalse($settings->getHasHeaderRow());
        $this->assertSame('Notes', $settings->getSheetName());
    }

    private function serializer(?object $answer = null): SerializerInterface
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->with($answer, 'json')->willReturn(self::ANSWER);

        return $serializer;
    }
}
