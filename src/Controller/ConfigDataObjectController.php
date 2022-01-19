<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Controller;

use Cron\CronExpression;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\AdminBundle\Helper\QueryParams;
use Pimcore\Bundle\DataHubBundle\Configuration\Dao;
use Pimcore\Bundle\DataImporterBundle\DataSource\Interpreter\InterpreterFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\DataLoaderFactory;
use Pimcore\Bundle\DataImporterBundle\DataSource\Loader\PushLoader;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Mapping\MappingConfigurationFactory;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\ClassificationStoreDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Mapping\Type\TransformationDataTypeService;
use Pimcore\Bundle\DataImporterBundle\Preview\PreviewService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportPreparationService;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Settings\ConfigurationPreparationService;
use Pimcore\Logger;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\QuantityValue\Unit;
use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/pimcoredataimporter/dataobject/config")
 */
class ConfigDataObjectController extends \Pimcore\Bundle\AdminBundle\Controller\AdminController
{
    public const CONFIG_NAME = 'plugin_datahub_config';

    /**
     * @var PreviewService
     */
    protected $previewService;

    /**
     * ConfigDataObjectController constructor.
     *
     * @param PreviewService $previewService
     */
    public function __construct(PreviewService $previewService)
    {
        $this->previewService = $previewService;
    }

    /**
     * @Route("/save")
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     *
     */
    public function saveAction(Request $request): ?JsonResponse
    {
        $this->checkPermission(self::CONFIG_NAME);

        try {
            $data = $request->get('data');
            $modificationDate = $request->get('modificationDate', 0);

            if ($modificationDate < Dao::getConfigModificationDate()) {
                throw new \Exception('The configuration was modified during editing, please reload the configuration and make your changes again');
            }

            $dataDecoded = json_decode($data, true);

            $name = $dataDecoded['general']['name'];
            $dataDecoded['general']['active'] = $dataDecoded['general']['active'] ?? false;
            $config = Dao::getByName($name);
            if (!$config->isAllowed('update')) {
                throw $this->createAccessDeniedHttpException();
            }
            $config->setConfiguration($dataDecoded);

            // @phpstan-ignore-next-line isAllowed return can changed now
            if ($config->isAllowed('read') && $config->isAllowed('update')) {
                $config->save();

                return $this->json(['success' => true, 'modificationDate' => Dao::getConfigModificationDate()]);
            } else {
                return $this->json(['success' => false, 'permissionError' => true]);
            }
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    protected function loadAvailableColumnHeaders(
        string $configName,
        array $config,
        InterpreterFactory $interpreterFactory
    ) {
        $previewFilePath = $this->previewService->getLocalPreviewFile($configName, $this->getAdminUser());
        if (is_file($previewFilePath)) {
            try {
                $interpreter = $interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig']);
                $dataPreview = $interpreter->previewData($previewFilePath);

                return $dataPreview->getDataColumnHeaders();
            } catch (\Exception $e) {
                Logger::warning($e);
            }
        }

        return [];
    }

    /**
     * @Route("/get")
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getAction(Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory
    ): JsonResponse {
        $this->checkPermission(self::CONFIG_NAME);

        $name = $request->get('name');
        $config = $configurationPreparationService->prepareConfiguration($name);

        return new JsonResponse(
            [
                'name' => $name,
                'configuration' => $config,
                'userPermissions' => $config['userPermissions'],
                'modificationDate' => Dao::getConfigModificationDate(),
                'columnHeaders' => $this->loadAvailableColumnHeaders($name, $config, $interpreterFactory)
            ]
        );
    }

    /**
     * @Route("/upload-preview", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function uploadPreviewDataAction(Request $request)
    {
        try {
            if (array_key_exists('Filedata', $_FILES)) {
                $filename = $_FILES['Filedata']['name'];
                $sourcePath = $_FILES['Filedata']['tmp_name'];
            } else {
                throw new \Exception('The filename of the preview data is empty');
            }

            if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                throw new \Exception('File is empty!');
            } elseif (!is_file($sourcePath)) {
                throw new \Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini and write permissions of your temporary directories.');
            }

            if (filesize($sourcePath) > 10485760) { //10 MB
                throw new \Exception('File it too big for preview file, please create a smaller one');
            }

            $this->previewService->writePreviewFile($request->get('config_name'), $sourcePath, $this->getAdminUser());
            @unlink($sourcePath);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            Logger::error($e);

            return $this->adminJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @Route("/copy-preview", methods={"POST"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param DataLoaderFactory $dataLoaderFactory
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function copyPreviewDataAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        DataLoaderFactory $dataLoaderFactory
    ) {
        try {
            $configName = $request->get('config_name');
            $currentConfig = $request->get('current_config');

            $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);
            $loader = $dataLoaderFactory->loadDataLoader($config['loaderConfig']);

            if ($loader instanceof PushLoader) {
                throw new \Exception('Cannot copy data from push loader for preview.');
            }

            $sourcePath = $loader->loadData();

            if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                throw new \Exception('File is empty!');
            } elseif (!is_file($sourcePath)) {
                throw new \Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini and write permissions of your temporary directories.');
            }

            if (filesize($sourcePath) > 10485760) { //10 MB
                throw new \Exception('File it too big for preview file, please create a smaller one');
            }

            $this->previewService->writePreviewFile($request->get('config_name'), $sourcePath, $this->getAdminUser());

            $loader->cleanup();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            Logger::error($e);

            return $this->adminJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @Route("/load-preview-data", methods={"POST"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     * @param Translator $translator
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function loadDataPreviewAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory,
        Translator $translator
    ) {
        $configName = $request->get('config_name');
        $currentConfig = $request->get('current_config');
        $recordNumber = intval($request->get('record_number'));

        $dataPreview = null;
        $hasData = false;
        $errorMessage = '';
        $previewFilePath = $this->previewService->getLocalPreviewFile($configName, $this->getAdminUser());
        if (is_file($previewFilePath)) {
            $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

            $mappedColumns = [];
            foreach (($config['mappingConfig'] ?? []) as $mapping) {
                $mappedColumns = array_merge($mappedColumns, ($mapping['dataSourceIndex'] ?? []));
            }
            $mappedColumns = array_unique($mappedColumns);

            try {
                $interpreter = $interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig']);

                if ($interpreter->fileValid($previewFilePath)) {
                    $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber, $mappedColumns);
                    $hasData = true;
                } else {
                    $errorMessage = $translator->trans('plugin_pimcore_datahub_data_importer_configpanel_preview_error_invalid_file', [], 'admin');
                }
            } catch (\Exception $e) {
                Logger::error($e);
                $errorMessage = $translator->trans('plugin_pimcore_datahub_data_importer_configpanel_preview_error_prefix', [], 'admin') . ': ' . $e->getMessage();
            }
        }

        return new JsonResponse([
            'dataPreview' => $dataPreview ? $dataPreview->getDataPreview() : [],
            'previewRecordIndex' => $dataPreview ? $dataPreview->getRecordNumber() : 0,
            'hasData' => $hasData,
            'errorMessage' => $errorMessage
        ]);
    }

    /**
     * @Route("/load-column-headers", methods={"POST"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     *
     * @return JsonResponse
     *
     * @throws \Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException|\Exception
     */
    public function loadAvailableColumnHeadersAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory
    ) {
        $configName = $request->get('config_name');
        $currentConfig = $request->get('current_config');
        $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

        return new JsonResponse([
            'columnHeaders' => $this->loadAvailableColumnHeaders($configName, $config, $interpreterFactory)
        ]);
    }

    /**
     * @Route("/load-transformation-result", methods={"POST"})
     *
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param MappingConfigurationFactory $factory
     * @param InterpreterFactory $interpreterFactory
     * @param ImportProcessingService $importProcessingService
     *
     * @return JsonResponse
     *
     * @throws InvalidConfigurationException|\Exception
     */
    public function loadTransformationResultPreviewsAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        MappingConfigurationFactory $factory,
        InterpreterFactory $interpreterFactory,
        ImportProcessingService $importProcessingService
    ) {
        $configName = $request->get('config_name');
        $currentConfig = $request->get('current_config');
        $recordNumber = intval($request->get('current_preview_record'));

        $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

        $previewFilePath = $this->previewService->getLocalPreviewFile($configName, $this->getAdminUser());
        $importDataRow = [];
        $transformationResults = [];
        $errorMessage = '';

        try {
            if (is_file($previewFilePath)) {
                $interpreter = $interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig']);

                $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber);
                $importDataRow = $dataPreview->getRawData();
            }

            $mapping = $factory->loadMappingConfiguration($configName, $config['mappingConfig'], true);

            foreach ($mapping as $index => $mappingConfiguration) {
                $transformationResults[] = $importProcessingService->generateTransformationResultPreview($importDataRow, $mappingConfiguration);
            }
        } catch (\Exception $e) {
            Logger::error($e);
            $errorMessage = $e->getMessage();
        }

        return new JsonResponse([
            'transformationResultPreviews' => $transformationResults,
            'errorMessage' => $errorMessage
        ]);
    }

    /**
     * @Route("/calculate-transformation-result-type", methods={"POST"})
     *
     * @param Request $request
     * @param MappingConfigurationFactory $factory
     * @param ImportProcessingService $importProcessingService
     *
     * @return JsonResponse
     */
    public function calculateTransformationResultTypeAction(
        Request $request,
        MappingConfigurationFactory $factory,
        ImportProcessingService $importProcessingService
    ) {
        try {
            $currentConfig = json_decode($request->get('current_config'), true);
            $configName = $request->get('config_name');
            $mappingConfiguration = $factory->loadMappingConfigurationItem($configName, $currentConfig, true);

            return new JsonResponse($importProcessingService->evaluateTransformationResultDataType($mappingConfiguration));
        } catch (InvalidConfigurationException $e) {
            return new JsonResponse('ERROR: ' . $e->getMessage());
        }
    }

    /**
     * @Route("/load-class-attributes", methods={"GET"})
     *
     * @param Request $request
     * @param TransformationDataTypeService $transformationDataTypeService
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function loadDataObjectAttributesAction(Request $request, TransformationDataTypeService $transformationDataTypeService)
    {
        $classId = $request->get('class_id');
        if (empty($classId)) {
            return new JsonResponse([]);
        }
        $loadAdvancedRelations = boolval($request->get('load_advanced_relations', false));

        $includeSystemRead = boolval($request->get('system_read', false));
        $includeSystemWrite = boolval($request->get('system_write', false));

        $transformationTargetType = $request->get('transformation_result_type', [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::NUMERIC]);

        return new JsonResponse([
            'attributes' => $transformationDataTypeService->getPimcoreDataTypes($classId, $transformationTargetType, $includeSystemRead, $includeSystemWrite, $loadAdvancedRelations)
        ]);
    }

    /**
     * @Route("/load-class-classificationstore-attributes", methods={"GET"})
     *
     * @param Request $request
     * @param TransformationDataTypeService $transformationDataTypeService
     *
     * @return JsonResponse
     */
    public function loadDataObjectClassificationStoreAttributesAction(Request $request, TransformationDataTypeService $transformationDataTypeService)
    {
        $classId = $request->get('class_id');
        if (empty($classId)) {
            return new JsonResponse([]);
        }

        return new JsonResponse([
            'attributes' => $transformationDataTypeService->getClassificationStoreAttributes($classId)
        ]);
    }

    /**
     * @Route("/load-class-classificationstore-keys", methods={"GET"})
     *
     * @param Request $request
     * @param ClassificationStoreDataTypeService $classificationStoreDataTypeService
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function loadDataObjectClassificationStoreKeysAction(Request $request, ClassificationStoreDataTypeService $classificationStoreDataTypeService)
    {
        $sortParams = QueryParams::extractSortingSettings(['sort' => $request->get('sort')]);

        $list = $classificationStoreDataTypeService->listClassificationStoreKeyList(
            strip_tags($request->get('class_id')),
            strip_tags($request->get('field_name')),
            strip_tags($request->get('transformation_result_type')),
            $sortParams['orderKey'] ?? 'name',
            $sortParams['order'] ?? 'ASC',
            intval($request->get('start')),
            intval($request->get('limit')),
            strip_tags($request->get('searchfilter')),
            strip_tags($request->get('filter'))
        );

        $data = [];
        foreach ($list as $config) {
            $item = [
                'keyId' => $config->getKeyId(),
                'groupId' => $config->getGroupId(),
                'keyName' => $config->getName(),
                'keyDescription' => $config->getDescription(),
                'id' => $config->getGroupId() . '-' . $config->getKeyId(),
                'sorter' => $config->getSorter(),
            ];

            $groupConfig = DataObject\Classificationstore\GroupConfig::getById($config->getGroupId());
            if ($groupConfig) {
                $item['groupName'] = $groupConfig->getName();
            }

            $data[] = $item;
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'total' => $list->getTotalCount()
        ]);
    }

    /**
     * @Route("/load-class-classificationstore-key-name", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function loadDataObjectClassificationStoreKeyNameAction(Request $request)
    {
        $keyId = $request->get('key_id');
        $keyParts = explode('-', $keyId);
        if (count($keyParts) == 2) {
            $keyGroupRelation = DataObject\Classificationstore\KeyGroupRelation::getByGroupAndKeyId($keyParts[0], $keyParts[1]);
            $group = DataObject\Classificationstore\GroupConfig::getById($keyGroupRelation->getGroupId());

            if ($keyGroupRelation && $group) {
                return new JsonResponse([
                    'groupName' => $group->getName(),
                    'keyName' => $keyGroupRelation->getName()
                ]);
            }
        }

        return new JsonResponse([
            'keyId' => $keyId
        ]);
    }

    /**
     * @Route("/start-import", methods={"PUT"})
     *
     * @param Request $request
     * @param ImportPreparationService $importPreparationService
     *
     * @return JsonResponse
     */
    public function startBatchImportAction(Request $request, ImportPreparationService $importPreparationService)
    {
        $configName = $request->get('config_name');
        $success = $importPreparationService->prepareImport($configName, true);

        return new JsonResponse([
            'success' => $success
        ]);
    }

    /**
     * @Route("/check-import-progress", methods={"GET"})
     *
     * @param Request $request
     * @param ImportProcessingService $importProcessingService
     *
     * @return JsonResponse
     */
    public function checkImportProgressAction(Request $request, ImportProcessingService $importProcessingService)
    {
        $configName = $request->get('config_name');

        return new JsonResponse($importProcessingService->getImportStatus($configName));
    }

    /**
     * @Route("/check-crontab", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function isCronExpressionValidAction(Request $request)
    {
        $message = '';
        $success = true;
        $cronExpression = $request->get('cron_expression');
        if (!empty($cronExpression)) {
            try {
                new CronExpression($cronExpression);
            } catch (\Exception $e) {
                $success = false;
                $message = $e->getMessage();
            }
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message
        ]);
    }

    /**
     * @Route("/cancel-execution", methods={"PUT"})
     *
     * @param Request $request
     * @param ImportProcessingService $importProcessingService
     *
     * @return JsonResponse
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function cancelExecutionAction(Request $request, ImportProcessingService $importProcessingService)
    {
        $configName = $request->get('config_name');
        $importProcessingService->cancelImportAndCleanupQueue($configName);

        return new JsonResponse([
            'success' => true
        ]);
    }

    /**
     * @Route("/upload-import-file", methods={"POST"})
     *
     * @param Request $request
     * @param FilesystemOperator $pimcoreDataImporterUploadStorage
     *
     * @return \Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse|JsonResponse
     *
     * @throws \League\Flysystem\FilesystemException
     */
    public function uploadImportFileAction(Request $request, FilesystemOperator $pimcoreDataImporterUploadStorage)
    {
        try {
            if (array_key_exists('Filedata', $_FILES)) {
                $filename = $_FILES['Filedata']['name'];
                $sourcePath = $_FILES['Filedata']['tmp_name'];
            } else {
                throw new \Exception('The filename of the upload data is empty');
            }

            $target = $this->getImportFilePath($request->get('config_name'));
            $pimcoreDataImporterUploadStorage->write($target, file_get_contents($sourcePath));

            @unlink($sourcePath);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            Logger::error($e);

            return $this->adminJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param string $configName
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getImportFilePath(string $configName): string
    {
        $configuration = Dao::getByName($configName);
        if (!$configuration) {
            throw new \Exception('Configuration ' . $configName . ' does not exist.');
        }

        $filePath = $configuration->getName() . '/upload.import';

        return $filePath;
    }

    /**
     * @Route("/has-import-file-uploaded", methods={"GET"})
     *
     * @param Request $request
     * @param Translator $translator
     * @param FilesystemOperator $pimcoreDataImporterUploadStorage
     *
     * @return \Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse|JsonResponse
     */
    public function hasImportFileUploadedAction(Request $request, Translator $translator, FilesystemOperator $pimcoreDataImporterUploadStorage)
    {
        try {
            $importFile = $this->getImportFilePath($request->get('config_name'));

            if ($pimcoreDataImporterUploadStorage->fileExists($importFile)) {
                return new JsonResponse(['success' => true, 'filePath' => $importFile, 'message' => $translator->trans('plugin_pimcore_datahub_data_importer_configpanel_type_upload_exists', [], 'admin')]);
            }

            return new JsonResponse(['success' => false, 'message' => $translator->trans('plugin_pimcore_datahub_data_importer_configpanel_type_upload_not_exists', [], 'admin')]);
        } catch (\Exception $e) {
            Logger::error($e);

            return $this->adminJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @Route("/load-unit-data", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function loadUnitDataAction(Request $request): JsonResponse
    {
        $unitList = new Unit\Listing();
        $unitList->setOrderKey('abbreviation');
        $data = [];
        foreach ($unitList as $unit) {
            $data[] = ['unitId' => $unit->getId(), 'abbreviation' => $unit->getAbbreviation()];
        }

        return new JsonResponse(['UnitList' => $data]);
    }
}
