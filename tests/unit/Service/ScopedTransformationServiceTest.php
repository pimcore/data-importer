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

namespace Pimcore\Bundle\DataImporterBundle\Tests\unit\Service;

use Codeception\Test\Unit;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\Hydrator\TransformationHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfiguration;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewAccess;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScopeResolverInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\TransformationService;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Transformation previews made while reviewing a change set read the change set's own preview data.
 */
class ScopedTransformationServiceTest extends Unit
{
    private const string CHANGE_SET = '01a08c7b-de2d-717b-93de-8aaee94a65a9';

    public function testAScopedResultPreviewReadsOnlyTheChangeSetsUpload(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())
            ->method('fileExists')
            ->with('car-import/_cs-' . self::CHANGE_SET . '-22.import')
            ->willReturn(false);
        $response = new TransformationResultPreviewsResponse([]);
        $hydrator = $this->createMock(TransformationHydratorInterface::class);
        $hydrator->expects($this->once())->method('hydrateResultPreviews')->with([])->willReturn($response);

        $result = $this->service($storage, ['interpreterConfig' => ['type' => 'csv']], $hydrator)
            ->loadTransformationResultPreviews(
                'car-import',
                ['interpreterConfig' => ['type' => 'csv'], 'processingConfig' => [], 'mappingConfig' => []],
                0,
                new PreviewScope(self::CHANGE_SET)
            );

        static::assertSame($response, $result);
    }

    public function testNoResultTypeForAChangeSetThatProposesNoSuchConfiguration(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->never())->method('fileExists');
        $hydrator = $this->createMock(TransformationHydratorInterface::class);
        $hydrator->expects($this->never())->method('hydrateResultType');

        $this->expectException(NotFoundHttpException::class);
        $this->service($storage, null, $hydrator)
            ->calculateTransformationResultType('car-import', [], new PreviewScope(self::CHANGE_SET));
    }

    private function service(
        FilesystemOperator $storage,
        ?array $proposal,
        TransformationHydratorInterface $hydrator,
    ): TransformationService {
        $configurations = $this->createStub(StoredConfigurationsInterface::class);
        $configurations->method('find')->willReturn(null);
        $scopes = $this->createStub(PreviewScopeResolverInterface::class);
        $scopes->method('proposal')->willReturn($proposal);

        $user = new User();
        $user->setId(22);
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($user);

        return new TransformationService(
            $hydrator,
            new PreviewService($storage),
            $security,
            new ConfigurationPreparationService(),
            new InterpreterFactory([]),
            new MappingConfigurationFactory(new MappingConfiguration(), [], []),
            // an empty mapping has nothing to process
            (new ReflectionClass(ImportProcessingService::class))->newInstanceWithoutConstructor(),
            new EventDispatcher(),
            new PreviewEventApplier(new EventDispatcher()),
            new PreviewAccess($configurations, $scopes, $security),
        );
    }
}
