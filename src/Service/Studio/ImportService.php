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
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\CronValidationEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ImportFileStatusEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ImportProgressEvent;
use Pimcore\Bundle\DataImporterBundle\Event\Studio\PreResponse\ImportStartEvent;
use Pimcore\Bundle\DataImporterBundle\Hydrator\ImportHydratorInterface;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportPreparationService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Schema\CronValidationResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportFileStatusResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportProgressResponse;
use Pimcore\Bundle\DataImporterBundle\Schema\ImportStartResponse;
use Pimcore\Bundle\DataImporterBundle\Utils\Constants\PermissionConstants;
use Pimcore\Bundle\StudioBackendBundle\Exception\Api\ForbiddenException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final readonly class ImportService implements ImportServiceInterface
{
    public function __construct(
        private ImportHydratorInterface $importHydrator,
        private ImportPreparationService $importPreparationService,
        private ImportProcessingService $importProcessingService,
        private FilesystemOperator $pimcoreDataImporterUploadStorage,
        private TranslatorInterface $translator,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

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

    public function uploadImportFile(string $name, UploadedFile $file): void
    {
        try {
            $this->loadConfigurationWithPermission(
                $name,
                PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_UPDATE
            );

            $this->pimcoreDataImporterUploadStorage->write(
                $name . '/upload.import',
                file_get_contents($file->getPathname())
            );
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function hasImportFileUploaded(string $name): ImportFileStatusResponse
    {
        $this->loadConfigurationWithPermission(
            $name,
            PermissionConstants::PLUGIN_DATA_IMPORTER_PERMISSION_READ
        );

        $importFile = $name . '/upload.import';

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
}
