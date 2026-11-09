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
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\DiscardRunController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\DownloadFailedRowsController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\LoadRunController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\StartRunController;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportRunResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportRunServiceInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\ListWaitingRunsController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\MergeLeavingOutController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\WithdrawRunController;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportWaitingRunsResponse;

class AdHocImportRunControllersTest extends Unit
{
    private const string ANSWER = '{"answer":true}';

    private function answer(): AdHocImportRunResponse
    {
        return new AdHocImportRunResponse(
            'running',
            2,
            0,
            0,
            0,
            0,
            1,
            'skip',
            'autumn.csv',
            'SKU',
            '/Products',
            4,
            'Jo Doe',
            null,
            false,
            0,
            0,
            null,
            []
        );
    }

    private function serializer(AdHocImportRunResponse $answer): SerializerInterface
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(
            function (mixed $data, string $format) use ($answer): string {
                $this->assertSame($answer, $data);
                $this->assertSame('json', $format);

                return self::ANSWER;
            }
        );

        return $serializer;
    }

    public function testAnImportIsStartedForTheFileAndItsParameters(): void
    {
        $parameters = new AdHocImportRunParameters(
            new AdHocImportDryRunParameters(7, 'PROD', AdHocImportDryRunParameters::MATCH_BY_FIELD, '0', 'sku'),
            'skip',
            'autumn.csv'
        );
        $answer = $this->answer();
        $service = $this->createMock(AdHocImportRunServiceInterface::class);
        $service->expects($this->once())->method('start')->with('file-1', $parameters)->willReturn($answer);

        $response = (new StartRunController($this->serializer($answer), $service))->startRun('file-1', $parameters);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::ANSWER, $response->getContent());
    }

    public function testARunIsAskedAfterByItsFile(): void
    {
        $answer = $this->answer();
        $service = $this->createMock(AdHocImportRunServiceInterface::class);
        $service->expects($this->once())->method('status')->with('file-1')->willReturn($answer);

        $response = (new LoadRunController($this->serializer($answer), $service))->loadRun('file-1');

        $this->assertSame(self::ANSWER, $response->getContent());
    }

    public function testARunIsForgottenAndAnswersNothing(): void
    {
        $service = $this->createMock(AdHocImportRunServiceInterface::class);
        $service->expects($this->once())->method('discard')->with('file-1');

        $controller = new DiscardRunController($this->createStub(SerializerInterface::class), $service);
        $response = $controller->discardRun('file-1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testTheFailedRowsAreDownloadedAsACsvAttachment(): void
    {
        $service = $this->createMock(AdHocImportRunServiceInterface::class);
        $service->expects($this->once())->method('failedRows')->with('file-1')->willReturn("row;error\n");

        $response = (new DownloadFailedRowsController($this->createStub(SerializerInterface::class), $service))
            ->downloadFailedRows('file-1');

        $this->assertSame("row;error\n", $response->getContent());
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=failed-rows.csv', $response->headers->get('Content-Disposition'));
    }

    public function testARunWaitingForADecisionIsMergedWithoutTheRowsThatCannotBeSaved(): void
    {
        $answer = $this->answer();
        $service = $this->createMock(AdHocImportRunServiceInterface::class);
        $service->expects($this->once())->method('mergeLeavingOut')->with('file-1')->willReturn($answer);

        $response = (new MergeLeavingOutController($this->serializer($answer), $service))->mergeLeavingOut('file-1');

        $this->assertSame(self::ANSWER, $response->getContent());
    }

    public function testARunWaitingForADecisionIsTakenBackAndAnswersNothing(): void
    {
        $service = $this->createMock(AdHocImportRunServiceInterface::class);
        $service->expects($this->once())->method('withdraw')->with('file-1');

        $response = (new WithdrawRunController($this->createStub(SerializerInterface::class), $service))
            ->withdrawRun('file-1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testTheRunsWaitingInAFolderAreListed(): void
    {
        $waiting = [['fileId' => 'file-1', 'fileName' => 'autumn.csv']];
        $service = $this->createMock(AdHocImportRunServiceInterface::class);
        $service->expects($this->once())->method('waitingRuns')->with(3)->willReturn($waiting);
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(
            function (mixed $data) use ($waiting): string {
                $this->assertInstanceOf(AdHocImportWaitingRunsResponse::class, $data);
                $this->assertSame($waiting, $data->getItems());

                return self::ANSWER;
            }
        );

        $response = (new ListWaitingRunsController($serializer, $service))->listWaitingRuns(3);

        $this->assertSame(self::ANSWER, $response->getContent());
    }
}
