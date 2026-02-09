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

use Cron\CronExpression;
use Exception;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\PushLoader;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ColumnHeadersEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\CronValidationEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\DataPreviewEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ImportFileStatusEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ImportProgressEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ImportStartEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\TransformationResultPreviewsEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\TransformationResultTypeEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\ConfigurationDetailHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Hydrator\ImportHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Hydrator\PreviewHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Hydrator\TransformationHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportPreparationService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Schema\ColumnHeadersResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ConfigurationDetail;
use Pimcore\Bundle\DataImporterBundle\Schema\CronValidationResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\DataPreviewResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportFileStatusResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportProgressResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportStartResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultPreviewsResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\TransformationResultTypeResponse;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ConflictException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\EnvironmentException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\MaxFileSizeExceededException;
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final readonly class ConfigurationService implements ConfigurationServiceInterface
{
    private const int MAX_FILE_SIZE = 10485760; // 10MB

    public function __construct(
        private ConfigurationDetailHydratorInterface $configurationDetailHydrator,
        private PreviewHydratorInterface $previewHydrator,
        private TransformationHydratorInterface $transformationHydrator,
        private ImportHydratorInterface $importHydrator,
        private PreviewService $previewService,
        private SecurityServiceInterface $securityService,
        private ConfigurationPreparationService $configurationPreparationService,
        private DataLoaderFactory $dataLoaderFactory,
        private InterpreterFactory $interpreterFactory,
        private MappingConfigurationFactory $mappingConfigurationFactory,
        private ImportPreparationService $importPreparationService,
        private ImportProcessingService $importProcessingService,
        private FilesystemOperator $pimcoreDataImporterUploadStorage,
        private TranslatorInterface $translator,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getConfiguration(string $name): ConfigurationDetail
    {
        $config = $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        return $this->configurationDetailHydrator->hydrate($config);
    }

    /**
     * @throws \Exception
     */
    public function saveConfiguration(string $name, array $configuration, int $modificationDate): ConfigurationDetail
    {
        $config = $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
        );

        if ($modificationDate < $config->getModificationDate()) {
            throw new ConflictException(
                'The configuration was modified during editing, please reload the configuration and make your changes again'
            );
        }

        $configuration['general']['active'] = $configuration['general']['active'] ?? false;

        $config->setConfiguration($configuration);
        $config->save();

        return $this->configurationDetailHydrator->hydrate($config);
    }

    /**
     * @throws \Exception
     */
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

    /**
     * @throws \Exception
     */
    public function copyPreviewData(string $name, ?array $currentConfig): void
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
        );

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration($name, $currentConfig);
        $loader = $this->dataLoaderFactory->loadDataLoader($preparedConfig['loaderConfig']);

        if ($loader instanceof PushLoader) {
            throw new EnvironmentException('Cannot copy data from push loader for preview.');
        }

        try {
            $sourcePath = $loader->loadData();

            if (!is_file($sourcePath)) {
                throw new EnvironmentException(
                    'Something went wrong, please check upload_max_filesize and post_max_size in your php.ini and write permissions of your temporary directories.'
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

    /**
     * @throws \Exception
     */
    public function loadPreviewData(string $name, ?array $currentConfig, int $recordNumber): DataPreviewResponse
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $user = $this->resolveCurrentUser();

        $previewFilePath = $this->previewService->getLocalPreviewFile($name, $user);

        if (!is_file($previewFilePath)) {
            throw new NotFoundHttpException('No preview data available. Please upload or copy preview data first.');
        }

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration($name, $currentConfig);

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
                'Preview file is not valid for the configured interpreter. Please re-upload or re-copy the preview data.'
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

    /**
     * @throws \Exception
     */
    public function loadColumnHeaders(string $name, ?array $currentConfig): ColumnHeadersResponse
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration($name, $currentConfig);

        $columnHeaders = $this->previewHydrator->loadAvailableColumnHeaders($name, $preparedConfig);

        $response = $this->previewHydrator->hydrateColumnHeaders($columnHeaders);

        $this->eventDispatcher->dispatch(
            new ColumnHeadersEvent($response),
            ColumnHeadersEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * @throws \Exception
     */
    public function loadTransformationResultPreviews(
        string $name,
        ?array $currentConfig,
        int $recordNumber
    ): TransformationResultPreviewsResponse {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $user = $this->resolveCurrentUser();

        $preparedConfig = $this->configurationPreparationService->prepareConfiguration($name, $currentConfig);

        $previewFilePath = $this->previewService->getLocalPreviewFile($name, $user);
        $importDataRow = [];

        if (is_file($previewFilePath)) {
            $interpreter = $this->interpreterFactory->loadInterpreter(
                $name,
                $preparedConfig['interpreterConfig'],
                $preparedConfig['processingConfig']
            );

            $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber);
            $importDataRow = $dataPreview->getRawData();
        }

        $mapping = $this->mappingConfigurationFactory->loadMappingConfiguration(
            $name,
            $preparedConfig['mappingConfig'],
            true
        );

        $transformationResults = [];
        foreach ($mapping as $mappingConfiguration) {
            $transformationResults[] = $this->importProcessingService->generateTransformationResultPreview(
                $importDataRow,
                $mappingConfiguration
            );
        }

        $response = $this->transformationHydrator->hydrateResultPreviews($transformationResults);

        $this->eventDispatcher->dispatch(
            new TransformationResultPreviewsEvent($response),
            TransformationResultPreviewsEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * @throws \Exception
     */
    public function calculateTransformationResultType(string $name, array $currentConfig): TransformationResultTypeResponse
    {
        $mappingConfiguration = $this->mappingConfigurationFactory->loadMappingConfigurationItem(
            $name,
            $currentConfig,
            true
        );

        $type = $this->importProcessingService->evaluateTransformationResultDataType($mappingConfiguration);

        $response = $this->transformationHydrator->hydrateResultType($type);

        $this->eventDispatcher->dispatch(
            new TransformationResultTypeEvent($response),
            TransformationResultTypeEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * @throws \Exception
     */
    public function startImport(string $name): ImportStartResponse
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
        );

        $success = $this->importPreparationService->prepareImport($name, true);

        $response = $this->importHydrator->hydrateImportStart($success);

        $this->eventDispatcher->dispatch(
            new ImportStartEvent($response),
            ImportStartEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * @throws \Exception
     */
    public function checkImportProgress(string $name): ImportProgressResponse
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $status = $this->importProcessingService->getImportStatus($name);

        $response = $this->importHydrator->hydrateImportProgress(
            (bool) $status['isRunning'],
            (int) $status['totalItems'],
            (int) $status['processedItems'],
            (float) $status['progress']
        );

        $this->eventDispatcher->dispatch(
            new ImportProgressEvent($response),
            ImportProgressEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * @throws \Exception
     */
    public function cancelExecution(string $name): void
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
        );

        $this->importProcessingService->cancelImportAndCleanupQueue($name);
    }

    public function validateCronExpression(string $cronExpression): CronValidationResponse
    {
        if (empty($cronExpression)) {
            $response = $this->importHydrator->hydrateCronValidation(true, '');

            $this->eventDispatcher->dispatch(
                new CronValidationEvent($response),
                CronValidationEvent::EVENT_NAME
            );

            return $response;
        }

        try {
            new CronExpression($cronExpression);

            $response = $this->importHydrator->hydrateCronValidation(true, '');
        } catch (Exception $e) {
            $response = $this->importHydrator->hydrateCronValidation(false, $e->getMessage());
        }

        $this->eventDispatcher->dispatch(
            new CronValidationEvent($response),
            CronValidationEvent::EVENT_NAME
        );

        return $response;
    }

    /**
     * @throws \Exception
     */
    public function uploadImportFile(string $name, UploadedFile $file): void
    {
        try {
            $this->loadConfigurationWithPermission(
                $name,
                PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
            );

            $target = $this->getImportFilePath($name);
            $this->pimcoreDataImporterUploadStorage->write($target, file_get_contents($file->getPathname()));
        } finally {
            @unlink($file->getPathname());
        }
    }

    /**
     * @throws \Exception
     */
    public function hasImportFileUploaded(string $name): ImportFileStatusResponse
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $importFile = $this->getImportFilePath($name);

        if ($this->pimcoreDataImporterUploadStorage->fileExists($importFile)) {
            $response = $this->importHydrator->hydrateImportFileStatus(
                true,
                $this->translator->trans(
                    'plugin_pimcore_datahub_data_importer_configpanel_type_upload_exists',
                    [],
                    'admin'
                ),
                $importFile
            );

            $this->eventDispatcher->dispatch(
                new ImportFileStatusEvent($response),
                ImportFileStatusEvent::EVENT_NAME
            );

            return $response;
        }

        $response = $this->importHydrator->hydrateImportFileStatus(
            false,
            $this->translator->trans(
                'plugin_pimcore_datahub_data_importer_configpanel_type_upload_not_exists',
                [],
                'admin'
            )
        );

        $this->eventDispatcher->dispatch(
            new ImportFileStatusEvent($response),
            ImportFileStatusEvent::EVENT_NAME
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

    /**
     * Get the import file storage path for a given configuration name.
     * Validates the configuration exists before building the path.
     */
    private function getImportFilePath(string $name): string
    {
        $configuration = Configuration::getByName($name);

        if (!$configuration) {
            throw new NotFoundHttpException(
                sprintf('Configuration with name "%s" not found', $name)
            );
        }

        return $configuration->getName() . '/upload.import';
    }
}
