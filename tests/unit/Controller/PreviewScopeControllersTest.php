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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Controller;

use Codeception\Test\Unit;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Config\CalculateTransformationResultTypeController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Config\CopyPreviewController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Config\LoadColumnHeadersController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Config\LoadPreviewController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Config\LoadTransformationResultController;
use Pimcore\Bundle\DataImporterBundle\Controller\Studio\Config\UploadPreviewController;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Schema\CalculateTransformationResultTypeParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\CopyPreviewParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\LoadPreviewParameters;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultTypeResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\PreviewDataServiceInterface;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\TransformationServiceInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Every preview endpoint hands the review's change set on, so its service reads the proposal.
 */
class PreviewScopeControllersTest extends Unit
{
    private const string CHANGE_SET = '01a08c7b-de2d-717b-93de-8aaee94a65a9';

    private const string CONTEXT = 'chat-7';

    private PreviewScope $scope;

    private SerializerInterface $serializer;

    protected function _before(): void
    {
        $this->scope = new PreviewScope(self::CHANGE_SET, self::CONTEXT);
        $this->serializer = $this->createStub(SerializerInterface::class);
        $this->serializer->method('serialize')->willReturn('{}');
    }

    public function testCopy(): void
    {
        $service = $this->createMock(PreviewDataServiceInterface::class);
        $service->expects($this->once())
            ->method('copyPreviewData')
            ->with('car-import', ['loaderConfig' => []], $this->scope);

        (new CopyPreviewController($this->serializer, $service))
            ->copyPreviewData('car-import', new CopyPreviewParameters(['loaderConfig' => []], ...$this->fields()));
    }

    public function testUploadReadsTheScopeFromTheFormFields(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'preview');
        $file = new UploadedFile($path, 'cars.csv', null, null, true);
        $request = new Request([], ['previewScope' => self::CHANGE_SET, 'previewContext' => self::CONTEXT]);
        $request->files->set('file', $file);
        $service = $this->createMock(PreviewDataServiceInterface::class);
        $service->expects($this->once())->method('uploadPreviewData')->with('car-import', $file, $this->scope);

        (new UploadPreviewController($this->serializer, $service))->uploadPreviewData('car-import', $request);
        @unlink($path);
    }

    public function testLoadPreview(): void
    {
        $service = $this->createMock(PreviewDataServiceInterface::class);
        $service->expects($this->once())
            ->method('loadPreviewData')
            ->with('car-import', null, 3, $this->scope)
            ->willReturn(new DataPreviewResponse([], 3));

        (new LoadPreviewController($this->serializer, $service))
            ->loadPreviewData('car-import', new LoadPreviewParameters(null, 3, ...$this->fields()));
    }

    public function testLoadColumnHeaders(): void
    {
        $service = $this->createMock(PreviewDataServiceInterface::class);
        $service->expects($this->once())
            ->method('loadColumnHeaders')
            ->with('car-import', null, $this->scope)
            ->willReturn(new ColumnHeadersResponse([]));

        (new LoadColumnHeadersController($this->serializer, $service))
            ->loadColumnHeaders('car-import', new CopyPreviewParameters(null, ...$this->fields()));
    }

    public function testLoadTransformationResult(): void
    {
        $service = $this->createMock(TransformationServiceInterface::class);
        $service->expects($this->once())
            ->method('loadTransformationResultPreviews')
            ->with('car-import', null, 3, $this->scope)
            ->willReturn(new TransformationResultPreviewsResponse([]));

        (new LoadTransformationResultController($this->serializer, $service))
            ->loadTransformationResult('car-import', new LoadPreviewParameters(null, 3, ...$this->fields()));
    }

    public function testCalculateTransformationResultType(): void
    {
        $service = $this->createMock(TransformationServiceInterface::class);
        $service->expects($this->once())
            ->method('calculateTransformationResultType')
            ->with('car-import', ['label' => 'vin'], $this->scope)
            ->willReturn(new TransformationResultTypeResponse('string'));

        (new CalculateTransformationResultTypeController($this->serializer, $service))
            ->calculateTransformationResultType(
                'car-import',
                new CalculateTransformationResultTypeParameters(['label' => 'vin'], ...$this->fields())
            );
    }

    /** @return array{previewScope: string, previewContext: string} */
    private function fields(): array
    {
        return ['previewScope' => self::CHANGE_SET, 'previewContext' => self::CONTEXT];
    }
}
