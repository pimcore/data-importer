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
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\DownloadSkippedRowsController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\AdHocImport\DryRunController;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\AdHocImportDryRunResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\AdHocImportDryRunServiceInterface;
use Symfony\Component\Serializer\SerializerInterface;

class AdHocImportDryRunControllersTest extends Unit
{
    private const string ANSWER = '{"answer":true}';

    private const string CSV = "sku;name\nSKU-1;Chair\n";

    public function testADryRunIsAskedForTheFileAndItsParameters(): void
    {
        $parameters = new AdHocImportDryRunParameters(
            7,
            'PROD',
            AdHocImportDryRunParameters::MATCH_BY_FIELD,
            '0',
            'sku'
        );
        $answer = new AdHocImportDryRunResponse('SKU', '/Products', [], []);

        $service = $this->createMock(AdHocImportDryRunServiceInterface::class);
        $service->expects($this->once())->method('dryRun')->with('file-1', $parameters)->willReturn($answer);

        $response = (new DryRunController($this->serializer($answer), $service))->dryRun('file-1', $parameters);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::ANSWER, $response->getContent());
    }

    public function testTheSkippedRowsAreDownloadedAsACsvAttachment(): void
    {
        $service = $this->createMock(AdHocImportDryRunServiceInterface::class);
        $service->expects($this->once())->method('skippedRows')
            ->with('file-1', AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES)
            ->willReturn(self::CSV);

        $response = (new DownloadSkippedRowsController($this->serializer(), $service))
            ->downloadSkippedRows('file-1', AdHocImportDryRunServiceInterface::WITHOUT_INVALID_VALUES);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::CSV, $response->getContent());
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=skipped-rows.csv', $response->headers->get('Content-Disposition'));
    }

    private function serializer(?object $answer = null): SerializerInterface
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->with($answer, 'json')->willReturn(self::ANSWER);

        return $serializer;
    }
}
