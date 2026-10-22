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
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Proposal\StoredConfigurationsInterface;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderInterface;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Hydrator\PreviewHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Installer;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewAccess;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewEventApplier;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScope;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewScopeResolverInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\PreviewDataService;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A preview made while reviewing a change set takes its settings from the change set, never
 * from what the request names.
 */
class ScopedPreviewDataServiceTest extends Unit
{
    private const string CHANGE_SET = '01a08c7b-de2d-717b-93de-8aaee94a65a9';

    private const string SCOPED_FILE = 'car-import/_cs-' . self::CHANGE_SET . '-22.import';

    /** above the 10 MB a fully loaded interpreter accepts, below the 50 MB of a streamed one */
    private const int ELEVEN_MB = 11 * 1024 * 1024;

    private FilesystemOperator $storage;

    protected function _before(): void
    {
        $this->storage = $this->createMock(FilesystemOperator::class);
    }

    public function testTheUploadLimitFollowsTheProposedInterpreter(): void
    {
        $this->storage->expects($this->never())->method('write');
        $service = $this->service(null, ['interpreterConfig' => ['type' => 'json']]);

        $this->expectException(MaxFileSizeExceededException::class);
        $service->uploadPreviewData('car-import', $this->upload(self::ELEVEN_MB), new PreviewScope(self::CHANGE_SET));
    }

    /** the proposal over a stored one: the stored interpreter's lower limit no longer applies */
    public function testAProposedStreamedInterpreterTakesTheLargerUpload(): void
    {
        $this->storage->expects($this->once())
            ->method('write')
            ->with(self::SCOPED_FILE, $this->anything());
        $stored = $this->createStub(Configuration::class);
        $stored->method('isAllowed')->willReturnCallback(static fn (string $p): bool => $p === 'read');
        $stored->method('getConfiguration')->willReturn(['interpreterConfig' => ['type' => 'json']]);
        $service = $this->service($stored, ['interpreterConfig' => ['type' => 'csv']]);

        $service->uploadPreviewData('car-import', $this->upload(self::ELEVEN_MB), new PreviewScope(self::CHANGE_SET));
    }

    public function testAScopedCopyLoadsThroughTheProposedLoaderNotTheRequestedOne(): void
    {
        $this->storage->expects($this->never())->method('write');
        $service = $this->service(null, ['loaderConfig' => ['type' => 'proposed-loader']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown loader type `proposed-loader`');
        $service->copyPreviewData(
            'car-import',
            ['loaderConfig' => ['type' => 'requested-loader']],
            new PreviewScope(self::CHANGE_SET)
        );
    }

    public function testAScopedCopyThroughAProposedLoaderNeedsUpdateOnTheStoredConfiguration(): void
    {
        $this->storage->expects($this->never())->method('write');
        $stored = $this->createStub(Configuration::class);
        $stored->method('isAllowed')->willReturnCallback(static fn (string $p): bool => $p === 'read');
        $stored->method('getConfiguration')->willReturn(['loaderConfig' => ['type' => 'sftp']]);
        $service = $this->service($stored, ['loaderConfig' => ['type' => 'proposed-loader']]);

        $this->expectException(ForbiddenException::class);
        $service->copyPreviewData('car-import', null, new PreviewScope(self::CHANGE_SET));
    }

    public function testAScopedCopyWritesTheFetchedDataToTheChangeSetsPreview(): void
    {
        $fetched = tempnam(sys_get_temp_dir(), 'fetched');
        file_put_contents($fetched, "sku;name\n1;Car\n");
        $this->storage->expects($this->once())
            ->method('write')
            ->with(self::SCOPED_FILE, "sku;name\n1;Car\n");
        $service = $this->service(
            null,
            ['loaderConfig' => ['type' => 'proposed-loader']],
            loaders: ['proposed-loader' => $this->loader($fetched)]
        );

        $service->copyPreviewData('car-import', null, new PreviewScope(self::CHANGE_SET));
    }

    public function testAScopedPreviewReadsOnlyTheChangeSetsUpload(): void
    {
        $this->storage->expects($this->once())
            ->method('fileExists')
            ->with(self::SCOPED_FILE)
            ->willReturn(false);
        $service = $this->service(null, ['interpreterConfig' => ['type' => 'csv']]);

        $this->expectException(NotFoundHttpException::class);
        $service->loadPreviewData('car-import', null, 0, new PreviewScope(self::CHANGE_SET));
    }

    public function testScopedColumnHeadersComeFromTheChangeSetsPreview(): void
    {
        $this->storage->expects($this->never())->method('write');
        $scope = new PreviewScope(self::CHANGE_SET);
        $hydrator = $this->createMock(PreviewHydratorInterface::class);
        $hydrator->expects($this->once())
            ->method('loadAvailableColumnHeaders')
            ->with('car-import', $this->isArray(), $scope)
            ->willReturn([['id' => '0', 'dataIndex' => 'col_0', 'label' => 'sku']]);
        $hydrator->method('hydrateColumnHeaders')->willReturn(new ColumnHeadersResponse([]));
        $service = $this->service(null, ['interpreterConfig' => ['type' => 'csv']], $hydrator);

        $service->loadColumnHeaders('car-import', ['interpreterConfig' => ['type' => 'csv']], $scope);
    }

    /**
     * @param array<string, DataLoaderInterface> $loaders
     */
    private function service(
        ?Configuration $stored,
        ?array $proposal,
        ?PreviewHydratorInterface $hydrator = null,
        array $loaders = [],
    ): PreviewDataService {
        $configurations = $this->createStub(StoredConfigurationsInterface::class);
        $configurations->method('find')->willReturn($stored);
        $scopes = $this->createStub(PreviewScopeResolverInterface::class);
        $scopes->method('proposal')->willReturn($proposal);

        $user = new User();
        $user->setId(22);
        $user->setPermissions([Installer::DATAHUB_ADAPTER_PERMISSION]);
        $security = $this->createStub(SecurityServiceInterface::class);
        $security->method('getCurrentUser')->willReturn($user);

        return new PreviewDataService(
            $hydrator ?? $this->createStub(PreviewHydratorInterface::class),
            new PreviewService($this->storage),
            $security,
            new ConfigurationPreparationService(),
            new DataLoaderFactory($loaders),
            new InterpreterFactory([]),
            new EventDispatcher(),
            new PreviewEventApplier(new EventDispatcher()),
            new PreviewAccess($configurations, $scopes, $security),
        );
    }

    private function loader(string $path): DataLoaderInterface
    {
        return new class($path) implements DataLoaderInterface {
            public function __construct(private readonly string $path)
            {
            }

            public function setSettings(array $settings): void
            {
                // the stub loads from its fixed path
            }

            public function loadData(): string
            {
                return $this->path;
            }

            public function cleanup(): void
            {
                @unlink($this->path);
            }
        };
    }

    private function upload(int $size): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'preview');
        $handle = fopen($path, 'wb');
        ftruncate($handle, $size);
        fclose($handle);

        return new UploadedFile($path, 'cars.csv', null, null, true);
    }
}
