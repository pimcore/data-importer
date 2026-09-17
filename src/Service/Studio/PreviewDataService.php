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

namespace Pimcore\Bundle\DataImporterBundle\Service\Studio;

use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\PushLoader;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ColumnHeadersEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\DataPreviewEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\PreviewHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\ConfigurationPermissionTrait;
use Pimcore\Bundle\DataImporterBundle\Service\Studio\Traits\CurrentUserResolverTrait;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
final readonly class PreviewDataService implements PreviewDataServiceInterface
{
    use ConfigurationPermissionTrait;
    use CurrentUserResolverTrait;

    private const int MAX_FILE_SIZE = 52428800; // 50MB

    private const int MAX_FULL_LOAD_FILE_SIZE = 10485760; // 10MB

    // interpreters that still load the entire preview file into memory,
    // so they keep the lower file size limit
    private const array FULL_LOAD_INTERPRETERS = ['json', 'xml', 'sql'];

    public function __construct(
        private PreviewHydratorInterface $previewHydrator,
        private PreviewService $previewService,
        private SecurityServiceInterface $securityService,
        private ConfigurationPreparationService $configurationPreparationService,
        private DataLoaderFactory $dataLoaderFactory,
        private InterpreterFactory $interpreterFactory,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function uploadPreviewData(string $name, UploadedFile $file): void
    {
        try {
            $this->loadConfigurationWithPermission(
                $name,
                PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
            );

            if ($file->getSize() === 0) {
                throw new EnvironmentException('Uploaded file is empty');
            }

            $maxFileSize = $this->getMaxPreviewFileSize(
                $this->configurationPreparationService->prepareConfiguration($name, null)
            );

            if ($file->getSize() > $maxFileSize) {
                throw new MaxFileSizeExceededException($maxFileSize);
            }

            $user = $this->resolveCurrentUser();

            $this->previewService->writePreviewFile($name, $file->getPathname(), $user);
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function copyPreviewData(string $name, ?array $currentConfig): void
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
        );

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration(
            $name,
            $currentConfig
        );
        $loader = $this->dataLoaderFactory->loadDataLoader($preparedConfig['loaderConfig']);

        if ($loader instanceof PushLoader) {
            throw new EnvironmentException('Cannot copy data from push loader for preview.');
        }

        try {
            $sourcePath = $loader->loadData();

            if (!is_file($sourcePath)) {
                throw new EnvironmentException(
                    'Something went wrong, please check upload_max_filesize '
                    . 'and post_max_size in your php.ini and write permissions '
                    . 'of your temporary directories.'
                );
            }

            if (filesize($sourcePath) < 1) {
                throw new EnvironmentException('File is empty');
            }

            $maxFileSize = $this->getMaxPreviewFileSize($preparedConfig);

            if (filesize($sourcePath) > $maxFileSize) {
                throw new MaxFileSizeExceededException($maxFileSize);
            }

            $user = $this->resolveCurrentUser();

            $this->previewService->writePreviewFile($name, $sourcePath, $user);
        } finally {
            $loader->cleanup();
        }
    }

    private function getMaxPreviewFileSize(array $preparedConfig): int
    {
        $interpreterType = $preparedConfig['interpreterConfig']['type'] ?? null;

        if (in_array($interpreterType, self::FULL_LOAD_INTERPRETERS, true)) {
            return self::MAX_FULL_LOAD_FILE_SIZE;
        }

        return self::MAX_FILE_SIZE;
    }

    public function loadPreviewData(
        string $name,
        ?array $currentConfig,
        int $recordNumber
    ): DataPreviewResponse {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $user = $this->resolveCurrentUser();

        $previewFilePath = $this->previewService->getLocalPreviewFile($name, $user);

        if ($previewFilePath === null || !is_file($previewFilePath)) {
            throw new NotFoundHttpException(
                'No preview data available. Please upload or copy preview data first.'
            );
        }

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration(
            $name,
            $currentConfig
        );

        $mappedColumns = [];
        foreach (($preparedConfig['mappingConfig'] ?? []) as $mapping) {
            if (isset($mapping['dataSourceIndex']) && is_array($mapping['dataSourceIndex'])) {
                $mappedColumns = array_merge($mappedColumns, $mapping['dataSourceIndex']);
            }
        }
        $mappedColumns = array_unique($mappedColumns);

        $interpreter = $this->interpreterFactory->loadInterpreter(
            $name,
            $preparedConfig['interpreterConfig'],
            $preparedConfig['processingConfig']
        );

        if (!$interpreter->fileValid($previewFilePath)) {
            throw new EnvironmentException(
                'Preview file is not valid for the configured interpreter. '
                . 'Please re-upload or re-copy the preview data.'
            );
        }

        $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber, $mappedColumns);

        $preview = $dataPreview->getDataPreview();
        if (!$this->previewHydrator->isValidJson($preview)) {
            @unlink($previewFilePath);

            throw new EnvironmentException('Invalid data preview. Deleted preview data.');
        }

        $response = $this->previewHydrator->hydrateDataPreview(
            $preview,
            $dataPreview->getRecordNumber()
        );

        $this->eventDispatcher->dispatch(
            new DataPreviewEvent($response),
            DataPreviewEvent::EVENT_NAME
        );

        return $response;
    }

    public function loadColumnHeaders(string $name, ?array $currentConfig): ColumnHeadersResponse
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration(
            $name,
            $currentConfig
        );

        $columnHeaders = $this->previewHydrator->loadAvailableColumnHeaders(
            $name,
            $preparedConfig
        );

        $response = $this->previewHydrator->hydrateColumnHeaders($columnHeaders);

        $this->eventDispatcher->dispatch(
            new ColumnHeadersEvent($response),
            ColumnHeadersEvent::EVENT_NAME
        );

        return $response;
    }
}
