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

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\PushLoader;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ColumnHeadersEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\DataPreviewEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\PreviewHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
final readonly class PreviewDataService implements PreviewDataServiceInterface
{
    private const int MAX_FILE_SIZE = 10485760; // 10MB

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

            if ($file->getSize() > self::MAX_FILE_SIZE) {
                throw new MaxFileSizeExceededException(self::MAX_FILE_SIZE);
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

            if (filesize($sourcePath) > self::MAX_FILE_SIZE) {
                throw new MaxFileSizeExceededException(self::MAX_FILE_SIZE);
            }

            $user = $this->resolveCurrentUser();

            $this->previewService->writePreviewFile($name, $sourcePath, $user);
        } finally {
            $loader->cleanup();
        }
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

        if (!is_file($previewFilePath)) {
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

    /**
     * Load and validate a configuration by name, checking the given permission.
     *
     * @throws NotFoundHttpException if the configuration does not exist
     * @throws ForbiddenException if the current user lacks the required permission
     */
    private function loadConfigurationWithPermission(string $name, string $permission): Configuration
    {
        $config = Configuration::getByName($name);

        if (!$config) {
            throw new NotFoundHttpException(
                sprintf('Configuration with name "%s" not found', $name)
            );
        }

        if (!$config->isAllowed($permission)) {
            throw new ForbiddenException(
                sprintf('Access denied to configuration "%s"', $name)
            );
        }

        return $config;
    }

    /**
     * Resolve the current user, ensuring it is a Pimcore User instance.
     *
     * @throws EnvironmentException if the current user cannot be resolved
     */
    private function resolveCurrentUser(): User
    {
        $user = $this->securityService->getCurrentUser();

        if (!$user instanceof User) {
            throw new EnvironmentException('Could not resolve current user');
        }

        return $user;
    }
}
