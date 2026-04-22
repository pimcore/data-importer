<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\DataImporterBundle\Controller;

use Cron\CronExpression;
use Exception;
use InvalidArgumentException;
use League\Flysystem\FilesystemOperator;
use Pimcore\Bundle\AdminBundle\Helper\QueryParams;
use Pimcore\Bundle\DataHubBundle\Configuration;
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
use Pimcore\Controller\Traits\JsonHelperTrait;
use Pimcore\Controller\UserAwareController;
use Pimcore\Helper\ParameterBagHelper;
use Pimcore\Logger;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\QuantityValue\Unit;
use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pimcoredataimporter/dataobject/config')]
class ConfigDataObjectController extends UserAwareController
{
    use JsonHelperTrait;

    public const CONFIG_NAME = 'plugin_datahub_config';

    private const CONFIG_DOES_NOT_EXIST_MSG = 'Configuration %s does not exist.';

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
     * @throws Exception
     */
    #[Route('/save')]
    public function saveAction(Request $request): ?JsonResponse
    {
        $this->checkPermission(self::CONFIG_NAME);

        try {
            $data = $request->request->get('data');
            $modificationDate = ParameterBagHelper::getInt($request->request, 'modificationDate');

            $dataDecoded = json_decode($data, true);

            $name = $dataDecoded['general']['name'];
            $dataDecoded['general']['active'] = $dataDecoded['general']['active'] ?? false;
            $config = Configuration::getByName($name);
            if (!$config) {
                throw new InvalidArgumentException(
                    sprintf(
                        self::CONFIG_DOES_NOT_EXIST_MSG,
                        $name
                    )
                );
            }
            if ($modificationDate < $config->getModificationDate()) {
                throw new Exception('The configuration was modified during editing, please reload the configuration and make your changes again');
            }
            if (!$config->isAllowed('update')) {
                throw $this->createAccessDeniedHttpException();
            }
            $config->setConfiguration($dataDecoded);

            if ($config->isAllowed('read') && $config->isAllowed('update')) {
                $config->save();

                return $this->json(['success' => true, 'modificationDate' => $config->getModificationDate()]);
            } else {
                return $this->json(['success' => false, 'permissionError' => true]);
            }
        } catch (Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * @param string $configName
     * @param array $config
     * @param InterpreterFactory $interpreterFactory
     *
     * @return array
     */
    protected function loadAvailableColumnHeaders(
        string $configName,
        array $config,
        InterpreterFactory $interpreterFactory
    ) {
        $previewFilePath = $this->previewService->getLocalPreviewFile($configName, $this->getPimcoreUser());
        if (is_file($previewFilePath)) {
            try {
                $interpreter = $interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig']);
                $dataPreview = $interpreter->previewData($previewFilePath);
                $columnHeaders = $dataPreview->getDataColumnHeaders();

                // Validate if the column headers are valid JSON. Otherwise take care of the preview file to be deleted.
                if (!$this->isValidJson($columnHeaders)) {
                    throw new \Exception('Invalid column headers.');
                }

                return $columnHeaders;
            } catch (Exception $e) {
                Logger::warning($e);
            }
        }

        return [];
    }

    protected function isValidJson(array $array): bool
    {
        json_encode($array);

        return json_last_error() === \JSON_ERROR_NONE;
    }

    /**
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route('/get')]
    public function getAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory
    ): JsonResponse {
        $this->checkPermission(self::CONFIG_NAME);

        $name = $request->query->get('name');
        $configuration = Configuration::getByName($name);
        if (!$configuration) {
            throw new InvalidArgumentException(
                sprintf(
                    self::CONFIG_DOES_NOT_EXIST_MSG,
                    $name
                )
            );
        }
        $config = $configurationPreparationService->prepareConfiguration($name);

        return new JsonResponse(
            [
                'name' => $name,
                'configuration' => $config,
                'userPermissions' => $config['userPermissions'],
                'modificationDate' => $configuration->getModificationDate(),
                'columnHeaders' => $this->loadAvailableColumnHeaders($name, $config, $interpreterFactory)
            ]
        );
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route('/upload-preview', methods: ['POST'])]
    public function uploadPreviewDataAction(Request $request)
    {
        try {
            if (array_key_exists('Filedata', $_FILES)) {
                $filename = $_FILES['Filedata']['name'];
                $sourcePath = $_FILES['Filedata']['tmp_name'];
            } else {
                throw new Exception('The filename of the preview data is empty');
            }

            if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                throw new Exception('File is empty!');
            } elseif (!is_file($sourcePath)) {
                throw new Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini and write permissions of your temporary directories.');
            }

            if (filesize($sourcePath) > 10485760) { //10 MB
                throw new Exception('File it too big for preview file, please create a smaller one');
            }

            $this->previewService->writePreviewFile($request->query->get('config_name'), $sourcePath, $this->getPimcoreUser());
            @unlink($sourcePath);

            return new JsonResponse(['success' => true]);
        } catch (Exception $e) {
            Logger::error($e);

            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param DataLoaderFactory $dataLoaderFactory
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route('/copy-preview', methods: ['POST'])]
    public function copyPreviewDataAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        DataLoaderFactory $dataLoaderFactory
    ) {
        try {
            $configName = $request->request->get('config_name');
            $currentConfig = $request->request->get('current_config');

            $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);
            $loader = $dataLoaderFactory->loadDataLoader($config['loaderConfig']);

            if ($loader instanceof PushLoader) {
                throw new Exception('Cannot copy data from push loader for preview.');
            }

            $sourcePath = $loader->loadData();

            if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                throw new Exception('File is empty!');
            } elseif (!is_file($sourcePath)) {
                throw new Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini and write permissions of your temporary directories.');
            }

            if (filesize($sourcePath) > 10485760) { //10 MB
                throw new Exception('File it too big for preview file, please create a smaller one');
            }

            $this->previewService->writePreviewFile($configName, $sourcePath, $this->getPimcoreUser());

            $loader->cleanup();

            return new JsonResponse(['success' => true]);
        } catch (Exception $e) {
            Logger::error($e);

            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     * @param Translator $translator
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route('/load-preview-data', methods: ['POST'])]
    public function loadDataPreviewAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory,
        Translator $translator
    ) {
        $configName = $request->request->get('config_name');
        $currentConfig = $request->request->get('current_config');
        $recordNumber = ParameterBagHelper::getInt($request->request, 'record_number');

        $dataPreview = null;
        $hasData = false;
        $errorMessage = '';
        $previewFilePath = $this->previewService->getLocalPreviewFile($configName, $this->getPimcoreUser());
        $dataPreviewData = [];
        if (is_file($previewFilePath)) {
            $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

            $mappedColumns = [];
            foreach (($config['mappingConfig'] ?? []) as $mapping) {
                if (isset($mapping['dataSourceIndex']) && is_array($mapping['dataSourceIndex'])) {
                    $mappedColumns = array_merge($mappedColumns, $mapping['dataSourceIndex']);
                }
            }
            $mappedColumns = array_unique($mappedColumns);

            try {
                $interpreter = $interpreterFactory->loadInterpreter($configName, $config['interpreterConfig'], $config['processingConfig']);

                if ($interpreter->fileValid($previewFilePath)) {
                    $dataPreview = $interpreter->previewData($previewFilePath, $recordNumber, $mappedColumns);
                    $hasData = true;

                    $preview = $dataPreview->getDataPreview();
                    if (!$this->isValidJson($preview)) {
                        unlink($previewFilePath);

                        throw new \Exception('Invalid data preview. Deleted preview data.');
                    }
                    $dataPreviewData = $preview;
                } else {
                    $errorMessage = $translator->trans('plugin_pimcore_datahub_data_importer_configpanel_preview_error_invalid_file', [], 'admin');
                }
            } catch (Exception $e) {
                Logger::error($e);
                $errorMessage = $translator->trans('plugin_pimcore_datahub_data_importer_configpanel_preview_error_prefix', [], 'admin') . ': ' . $e->getMessage();
            }
        }

        return new JsonResponse([
            'dataPreview' => $dataPreviewData,
            'previewRecordIndex' => $dataPreview ? $dataPreview->getRecordNumber() : 0,
            'hasData' => $hasData,
            'errorMessage' => $errorMessage
        ]);
    }

    /**
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param InterpreterFactory $interpreterFactory
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route('/load-column-headers', methods: ['POST'])]
    public function loadAvailableColumnHeadersAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        InterpreterFactory $interpreterFactory
    ) {
        $configName = $request->request->get('config_name');
        $currentConfig = $request->request->get('current_config');
        $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

        return new JsonResponse([
            'columnHeaders' => $this->loadAvailableColumnHeaders($configName, $config, $interpreterFactory)
        ]);
    }

    /**
     * @param Request $request
     * @param ConfigurationPreparationService $configurationPreparationService
     * @param MappingConfigurationFactory $factory
     * @param InterpreterFactory $interpreterFactory
     * @param ImportProcessingService $importProcessingService
     *
     * @return JsonResponse
     *
     * @throws InvalidConfigurationException|Exception
     */
    #[Route('/load-transformation-result', methods: ['POST'])]
    public function loadTransformationResultPreviewsAction(
        Request $request,
        ConfigurationPreparationService $configurationPreparationService,
        MappingConfigurationFactory $factory,
        InterpreterFactory $interpreterFactory,
        ImportProcessingService $importProcessingService
    ) {
        $configName = $request->request->get('config_name');
        $currentConfig = $request->request->get('current_config');
        $recordNumber = ParameterBagHelper::getInt($request->request, 'current_preview_record');

        $config = $configurationPreparationService->prepareConfiguration($configName, $currentConfig);

        $previewFilePath = $this->previewService->getLocalPreviewFile($configName, $this->getPimcoreUser());
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
        } catch (Exception $e) {
            Logger::error($e);
            $errorMessage = $e->getMessage();
        }

        return new JsonResponse([
            'transformationResultPreviews' => $transformationResults,
            'errorMessage' => $errorMessage
        ]);
    }

    /**
     * @param Request $request
     * @param MappingConfigurationFactory $factory
     * @param ImportProcessingService $importProcessingService
     *
     * @return JsonResponse
     */
    #[Route('/calculate-transformation-result-type', methods: ['POST'])]
    public function calculateTransformationResultTypeAction(
        Request $request,
        MappingConfigurationFactory $factory,
        ImportProcessingService $importProcessingService
    ) {
        try {
            $currentConfig = json_decode(
                $request->request->get('current_config'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $configName = $request->request->get('config_name');
            $mappingConfiguration = $factory->loadMappingConfigurationItem($configName, $currentConfig, true);

            return new JsonResponse($importProcessingService->evaluateTransformationResultDataType($mappingConfiguration));
        } catch (Exception | InvalidConfigurationException $e) {
            return new JsonResponse('ERROR: ' . $e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param TransformationDataTypeService $transformationDataTypeService
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route('/load-class-attributes', methods: ['GET'])]
    public function loadDataObjectAttributesAction(Request $request, TransformationDataTypeService $transformationDataTypeService)
    {
        $classId = $request->query->get('class_id');
        if (empty($classId)) {
            return new JsonResponse([]);
        }
        $loadAdvancedRelations = $request->query->getBoolean('load_advanced_relations');
        $includeSystemRead = $request->query->getBoolean('system_read');
        $includeSystemWrite = $request->query->getBoolean('system_write');
        $transformationTargetType = $request->query->get('transformation_result_type');
        if (!$transformationTargetType) {
            $transformationTargetType = [TransformationDataTypeService::DEFAULT_TYPE, TransformationDataTypeService::NUMERIC];
        }

        return new JsonResponse([
            'attributes' => $transformationDataTypeService->getPimcoreDataTypes($classId, $transformationTargetType, $includeSystemRead, $includeSystemWrite, $loadAdvancedRelations)
        ]);
    }

    /**
     * @param Request $request
     * @param TransformationDataTypeService $transformationDataTypeService
     *
     * @return JsonResponse
     */
    #[Route('/load-class-classificationstore-attributes', methods: ['GET'])]
    public function loadDataObjectClassificationStoreAttributesAction(Request $request, TransformationDataTypeService $transformationDataTypeService)
    {
        $classId = $request->query->get('class_id');
        if (empty($classId)) {
            return new JsonResponse([]);
        }

        return new JsonResponse([
            'attributes' => $transformationDataTypeService->getClassificationStoreAttributes($classId)
        ]);
    }

    /**
     * @param Request $request
     * @param ClassificationStoreDataTypeService $classificationStoreDataTypeService
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route('/load-class-classificationstore-keys', methods: ['GET'])]
    public function loadDataObjectClassificationStoreKeysAction(Request $request, ClassificationStoreDataTypeService $classificationStoreDataTypeService)
    {
        $sortParams = QueryParams::extractSortingSettings(['sort' => $request->query->get('sort')]);

        $list = $classificationStoreDataTypeService->listClassificationStoreKeyList(
            strip_tags($request->query->get('class_id')),
            strip_tags($request->query->get('field_name')),
            strip_tags($request->query->get('transformation_result_type')),
            $sortParams['orderKey'] ?? 'name',
            $sortParams['order'] ?? 'ASC',
            ParameterBagHelper::getInt($request->query, 'start'),
            ParameterBagHelper::getInt($request->query, 'limit'),
            strip_tags($request->query->get('searchfilter')),
            strip_tags($request->query->get('filter'))
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
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    #[Route('/load-class-classificationstore-key-name', methods: ['GET'])]
    public function loadDataObjectClassificationStoreKeyNameAction(Request $request)
    {
        $keyId = $request->query->get('key_id');
        $keyParts = explode('-', $keyId);
        if (count($keyParts) === 2) {
            $keyGroupRelation = DataObject\Classificationstore\KeyGroupRelation::getByGroupAndKeyId((int)$keyParts[0], (int)$keyParts[1]);
            if ($keyGroupRelation) {
                $group = DataObject\Classificationstore\GroupConfig::getById($keyGroupRelation->getGroupId());

                if ($group) {
                    return new JsonResponse([
                        'groupName' => $group->getName(),
                        'keyName' => $keyGroupRelation->getName()
                    ]);
                }
            }
        }

        return new JsonResponse([
            'keyId' => $keyId
        ]);
    }

    /**
     * @param Request $request
     * @param ImportPreparationService $importPreparationService
     *
     * @return JsonResponse
     */
    #[Route('/start-import', methods: ['PUT'])]
    public function startBatchImportAction(Request $request, ImportPreparationService $importPreparationService)
    {
        $configName = $request->request->get('config_name');
        $success = $importPreparationService->prepareImport($configName, true);

        return new JsonResponse([
            'success' => $success
        ]);
    }

    /**
     * @param Request $request
     * @param ImportProcessingService $importProcessingService
     *
     * @return JsonResponse
     */
    #[Route('/check-import-progress', methods: ['GET'])]
    public function checkImportProgressAction(Request $request, ImportProcessingService $importProcessingService)
    {
        $configName = $request->query->get('config_name');

        return new JsonResponse($importProcessingService->getImportStatus($configName));
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    #[Route('/check-crontab', methods: ['GET'])]
    public function isCronExpressionValidAction(Request $request)
    {
        $message = '';
        $success = true;
        $cronExpression = $request->query->get('cron_expression');
        if (!empty($cronExpression)) {
            try {
                new CronExpression($cronExpression);
            } catch (Exception $e) {
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
     * @param Request $request
     * @param ImportProcessingService $importProcessingService
     *
     * @return JsonResponse
     */
    #[Route('/cancel-execution', methods: ['PUT'])]
    public function cancelExecutionAction(Request $request, ImportProcessingService $importProcessingService)
    {
        $configName = $request->request->get('config_name');
        $importProcessingService->cancelImportAndCleanupQueue($configName);

        return new JsonResponse([
            'success' => true
        ]);
    }

    /**
     * @param Request $request
     * @param FilesystemOperator $pimcoreDataImporterUploadStorage
     *
     * @return JsonResponse
     *
     * @throws \League\Flysystem\FilesystemException
     */
    #[Route('/upload-import-file', methods: ['POST'])]
    public function uploadImportFileAction(Request $request, FilesystemOperator $pimcoreDataImporterUploadStorage)
    {
        try {
            if (array_key_exists('Filedata', $_FILES)) {
                $filename = $_FILES['Filedata']['name'];
                $sourcePath = $_FILES['Filedata']['tmp_name'];
            } else {
                throw new Exception('The filename of the upload data is empty');
            }

            $target = $this->getImportFilePath($request->query->get('config_name'));
            $pimcoreDataImporterUploadStorage->write($target, file_get_contents($sourcePath));

            @unlink($sourcePath);

            return new JsonResponse(['success' => true]);
        } catch (Exception $e) {
            Logger::error($e);

            return $this->jsonResponse([
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
     * @throws Exception
     */
    protected function getImportFilePath(string $configName): string
    {
        $configuration = Configuration::getByName($configName);
        if (!$configuration) {
            throw new InvalidArgumentException(
                sprintf(
                    self::CONFIG_DOES_NOT_EXIST_MSG,
                    $configName
                )
            );
        }

        return $configuration->getName() . '/upload.import';
    }

    /**
     * @param Request $request
     * @param Translator $translator
     * @param FilesystemOperator $pimcoreDataImporterUploadStorage
     *
     * @return JsonResponse
     */
    #[Route('/has-import-file-uploaded', methods: ['GET'])]
    public function hasImportFileUploadedAction(Request $request, Translator $translator, FilesystemOperator $pimcoreDataImporterUploadStorage)
    {
        try {
            $importFile = $this->getImportFilePath($request->query->get('config_name'));

            if ($pimcoreDataImporterUploadStorage->fileExists($importFile)) {
                return new JsonResponse(['success' => true, 'filePath' => $importFile, 'message' => $translator->trans('plugin_pimcore_datahub_data_importer_configpanel_type_upload_exists', [], 'admin')]);
            }

            return new JsonResponse(['success' => false, 'message' => $translator->trans('plugin_pimcore_datahub_data_importer_configpanel_type_upload_not_exists', [], 'admin')]);
        } catch (Exception $e) {
            Logger::error($e);

            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    #[Route('/load-unit-data', methods: ['GET'])]
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
